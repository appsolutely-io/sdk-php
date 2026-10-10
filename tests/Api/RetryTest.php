<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\ConflictException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\RateLimitedException;
use Appsolutely\Sdk\Exception\ServiceUnavailableException;
use Appsolutely\Sdk\Exception\TransportException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A keyed write carries an Idempotency-Key, the same one on every retry, so
 * a retry after a lost answer is answered with the first answer instead of
 * running twice. Retries are few, wait what the site asks, and are made only
 * where repeating the request cannot do the work twice.
 */
final class RetryTest extends TestCase
{
    private const string UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function testAKeyedWriteSendsAGeneratedKeyAndSaysWhetherTheAnswerWasReplayed(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'addr-1'], 201, ['Idempotent-Replayed' => 'true']));

        $response = $provider->client()->forMember('member-token')->api()->raw()->postIdempotent('/api/v1/me/addresses', ['line1' => '1 Main St']);

        $request = $provider->siteRequests()[0];
        self::assertMatchesRegularExpression(self::UUID_V4, $request->getHeaderLine('Idempotency-Key'));
        self::assertSame($request->getHeaderLine('Idempotency-Key'), $response->idempotencyKey);
        self::assertSame('{"line1":"1 Main St"}', (string) $request->getBody());
        self::assertTrue($response->replayed);
        self::assertSame(['id' => 'addr-1'], $response->data);
    }

    public function testACallerSuppliedKeyIsSentAsItIs(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1'], 201));

        $response = $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', ['title' => 'T'], idempotencyKey: 'order 42: create article');

        self::assertSame('order 42: create article', $provider->siteRequests()[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame('order 42: create article', $response->idempotencyKey);
        self::assertFalse($response->replayed);
    }

    public function testEveryKeyedWriteGetsAKeyOfItsOwn(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json([], 201));
        $api = $provider->client()->api()->raw();

        $api->postIdempotent('/api/v1/articles', ['title' => 'A']);
        $api->postIdempotent('/api/v1/articles', ['title' => 'A']);

        [$first, $second] = $provider->siteRequests();
        self::assertNotSame($first->getHeaderLine('Idempotency-Key'), $second->getHeaderLine('Idempotency-Key'));
    }

    /**
     * The site's rule: 1 to 255 printable ASCII characters.
     *
     * @return iterable<string, array{string}>
     */
    public static function refusedKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'longer than 255 characters' => [str_repeat('k', 256)];
        yield 'a line break' => ["key\r\nX-Forged: 1"];
        yield 'beyond ASCII' => ["cl\u{00E9}"];
        yield 'a leading space, which a header would lose' => [' key'];
        yield 'a trailing space, which a header would lose' => ['key '];
    }

    #[DataProvider('refusedKeys')]
    public function testAKeyTheSiteWouldRefuseIsRefusedBeforeSending(string $key): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json([], 201));

        try {
            $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', [], idempotencyKey: $key);
            self::fail('The key was accepted.');
        } catch (InvalidArgumentValueException) {
            self::assertSame([], $provider->siteRequests());
        }
    }

    public function testANetworkFailureIsRetriedWithTheSameKeyAndRequestId(): void
    {
        $provider = self::answeringInTurn(
            static fn(): never => throw new class ('connection reset') extends RuntimeException implements ClientExceptionInterface {},
            static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1'], 201),
        );

        $response = $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', ['title' => 'T']);

        [$first, $second] = $provider->siteRequests();
        self::assertSame($first->getHeaderLine('Idempotency-Key'), $second->getHeaderLine('Idempotency-Key'));
        self::assertSame($first->getHeaderLine('X-Request-Id'), $second->getHeaderLine('X-Request-Id'));
        self::assertSame((string) $first->getBody(), (string) $second->getBody());
        self::assertSame(201, $response->status);
        self::assertCount(1, $provider->sleeper->sleeps);
        // The first backoff is half a second, jittered between half and all of it.
        self::assertGreaterThanOrEqual(0.25, $provider->sleeper->sleeps[0]);
        self::assertLessThanOrEqual(0.5, $provider->sleeper->sleeps[0]);
    }

    public function testARequestStillInFlightIsRetriedAfterTheSecondsTheSiteAsks(): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(409, 'idempotency-request-in-flight', headers: ['Retry-After' => '2']),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1'], 201, ['Idempotent-Replayed' => 'true']),
        );

        $response = $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', ['title' => 'T'], idempotencyKey: 'k-1');

        self::assertSame([2.0], $provider->sleeper->sleeps);
        self::assertSame(['k-1', 'k-1'], array_map(static fn(RequestInterface $request): string => $request->getHeaderLine('Idempotency-Key'), $provider->siteRequests()));
        self::assertTrue($response->replayed);
    }

    public function testARateLimitIsWaitedOutForRetryAfter(): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(429, 'too-many-requests', headers: ['Retry-After' => '3']),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json([], 201),
        );

        $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', []);

        self::assertSame([3.0], $provider->sleeper->sleeps);
    }

    public function testARateLimitWithoutRetryAfterWaitsUntilTheSpentQuotaTurnsOver(): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(429, 'too-many-requests', headers: ['RateLimit' => '"api:authenticated";r=0;t=5']),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json([]),
        );

        $provider->client()->api()->raw()->get('/api/v1/articles/a-1');

        self::assertSame([5.0], $provider->sleeper->sleeps);
    }

    public function testAnUnavailableSiteIsRetried(): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(503, 'standing-unavailable', headers: ['Retry-After' => '1']),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json([], 201),
        );

        $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', []);

        self::assertSame([1.0], $provider->sleeper->sleeps);
        self::assertCount(2, $provider->siteRequests());
    }

    public function testAReadIsRetriedLikeAKeyedWrite(): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(503, 'standing-unavailable'),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json(['data' => []]),
        );

        $page = $provider->client()->api()->raw()->page('/api/v1/articles');

        self::assertSame([], $page->items);
        self::assertCount(2, $provider->siteRequests());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function finalRefusals(): iterable
    {
        yield 'invalid key' => [400, 'idempotency-key-invalid'];
        yield 'unauthenticated' => [401, 'unauthenticated'];
        yield 'forbidden' => [403, 'missing-ability'];
        yield 'not found' => [404, 'not-found'];
        yield 'a business conflict' => [409, 'subscription-inactive'];
        yield 'validation' => [422, 'validation-failed'];
        yield 'a reused key' => [422, 'idempotency-key-reused'];
        yield 'an unknown outcome' => [500, 'idempotency-outcome-unknown'];
    }

    /**
     * A 500 under a key means the outcome is unknown: only a new key, after
     * reading the current state, may try again.
     */
    #[DataProvider('finalRefusals')]
    public function testARefusalThatARetryCannotChangeIsThrownAtOnce(int $status, string $slug): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem($status, $slug, headers: ['Retry-After' => '1']));

        try {
            $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', []);
            self::fail('No exception was thrown.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->hasType($slug));
        }
        self::assertCount(1, $provider->siteRequests());
        self::assertSame([], $provider->sleeper->sleeps);
    }

    public function testRetriesStopAtTheLimitAndTheLastRefusalIsThrown(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem(503, 'standing-unavailable'));

        try {
            $provider->client(retryPolicy: new RetryPolicy(maxRetries: 3, sleeper: $provider->sleeper))->api()->raw()->postIdempotent('/api/v1/articles', []);
            self::fail('No exception was thrown.');
        } catch (ServiceUnavailableException) {
        }

        self::assertCount(4, $provider->siteRequests());
        self::assertCount(3, $provider->sleeper->sleeps);
        // Each backoff doubles, jittered between half and all of it.
        foreach ([0.5, 1.0, 2.0] as $index => $ceiling) {
            self::assertGreaterThanOrEqual($ceiling / 2, $provider->sleeper->sleeps[$index]);
            self::assertLessThanOrEqual($ceiling, $provider->sleeper->sleeps[$index]);
        }
    }

    public function testANetworkFailureOnTheLastAttemptIsThrown(): void
    {
        $provider = self::answering(static fn(): never => throw new class ('timed out') extends RuntimeException implements ClientExceptionInterface {});

        $this->expectException(TransportException::class);

        $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', []);
    }

    public function testRetriesCanBeTurnedOff(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem(503, 'standing-unavailable', headers: ['Retry-After' => '1']));

        try {
            $provider->client(retryPolicy: RetryPolicy::none())->api()->raw()->postIdempotent('/api/v1/articles', []);
            self::fail('No exception was thrown.');
        } catch (ServiceUnavailableException) {
        }

        self::assertCount(1, $provider->siteRequests());
    }

    public function testAWaitLongerThanTheCeilingIsNotMade(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem(429, 'too-many-requests', headers: ['Retry-After' => '3600']));

        try {
            $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', []);
            self::fail('No exception was thrown.');
        } catch (RateLimitedException $exception) {
            self::assertSame(3600, $exception->retryAfter);
        }

        self::assertCount(1, $provider->siteRequests());
        self::assertSame([], $provider->sleeper->sleeps);
    }

    /**
     * Without a key, a repeated write could do the work twice.
     *
     * @return iterable<string, array{string}>
     */
    public static function unkeyedWrites(): iterable
    {
        yield 'POST' => ['post'];
        yield 'PUT' => ['put'];
        yield 'PATCH' => ['patch'];
        yield 'DELETE' => ['delete'];
    }

    #[DataProvider('unkeyedWrites')]
    public function testAWriteWithoutAKeyIsNeverRetried(string $method): void
    {
        $provider = self::answering(static fn(): never => throw new class ('connection reset') extends RuntimeException implements ClientExceptionInterface {});
        $api = $provider->client()->api()->raw();

        try {
            match ($method) {
                'post' => $api->post('/api/v1/articles', []),
                'put' => $api->put('/api/v1/articles/a-1', []),
                'patch' => $api->patch('/api/v1/articles/a-1', []),
                'delete' => $api->delete('/api/v1/articles/a-1'),
                default => throw new \LogicException('Unknown method ' . $method),
            };
            self::fail('No exception was thrown.');
        } catch (TransportException) {
        }

        self::assertCount(1, $provider->siteRequests());
        self::assertFalse($provider->siteRequests()[0]->hasHeader('Idempotency-Key'));
    }

    public function testAnUnkeyedWriteIsNotRetriedOnAnUnavailableSiteEither(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem(503, 'standing-unavailable', headers: ['Retry-After' => '1']));

        try {
            $provider->client()->api()->raw()->post('/api/v1/articles', []);
            self::fail('No exception was thrown.');
        } catch (ServiceUnavailableException) {
        }

        self::assertCount(1, $provider->siteRequests());
    }

    public function testEachRetryIsLogged(): void
    {
        $logger = new RecordingLogger();
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(409, 'idempotency-request-in-flight', headers: ['Retry-After' => '2']),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json([], 201),
        );

        $provider->client(logger: $logger)->api()->raw()->postIdempotent('/api/v1/articles', [], idempotencyKey: 'secret-ish-key');

        self::assertCount(1, $logger->records);
        self::assertStringContainsString('409', $logger->records[0]['message']);
        self::assertStringNotContainsString('secret-ish-key', $logger->records[0]['message'] . json_encode($logger->records[0]['context']));
    }

    public function testAConflictOutsideAKeyedWriteIsThrown(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem(409, 'idempotency-request-in-flight', headers: ['Retry-After' => '1']));

        try {
            $provider->client()->api()->raw()->get('/api/v1/articles/a-1');
            self::fail('No exception was thrown.');
        } catch (ConflictException) {
        }

        self::assertCount(1, $provider->siteRequests());
    }

    /**
     * @return iterable<string, array{\Closure(): RetryPolicy}>
     */
    public static function refusedPolicies(): iterable
    {
        yield 'negative retries' => [static fn(): RetryPolicy => new RetryPolicy(maxRetries: -1)];
        yield 'more than ten retries' => [static fn(): RetryPolicy => new RetryPolicy(maxRetries: 11)];
        yield 'no backoff' => [static fn(): RetryPolicy => new RetryPolicy(baseDelay: 0.0)];
        yield 'a ceiling below a second' => [static fn(): RetryPolicy => new RetryPolicy(maxDelay: 0)];
        yield 'a ceiling beyond five minutes' => [static fn(): RetryPolicy => new RetryPolicy(maxDelay: 301)];
        yield 'a backoff above the ceiling' => [static fn(): RetryPolicy => new RetryPolicy(baseDelay: 10.0, maxDelay: 5)];
    }

    /**
     * @param \Closure(): RetryPolicy $policy
     */
    #[DataProvider('refusedPolicies')]
    public function testAPolicyOutsideItsBoundsIsRefused(\Closure $policy): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        $policy();
    }

    /**
     * @param \Closure(FakeProvider, RequestInterface): ResponseInterface $answer
     */
    private static function answering(\Closure $answer): FakeProvider
    {
        $provider = new FakeProvider();
        $provider->site = static fn(RequestInterface $request): ResponseInterface => $answer($provider, $request);

        return $provider;
    }

    /**
     * Answers with each closure in turn, the last one from then on.
     *
     * @param \Closure(FakeProvider): ResponseInterface ...$answers
     */
    private static function answeringInTurn(\Closure ...$answers): FakeProvider
    {
        $provider = new FakeProvider();
        $provider->site = static function () use ($provider, &$answers): ResponseInterface {
            $answer = count($answers) > 1 ? array_shift($answers) : $answers[0];

            return $answer($provider);
        };

        return $provider;
    }
}

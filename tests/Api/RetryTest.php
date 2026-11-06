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
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
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
        self::assertMatchesRegularExpression(self::UUID_V4, (string) $response->idempotencyKey);
        self::assertSame('"' . $response->idempotencyKey . '"', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('{"line1":"1 Main St"}', (string) $request->getBody());
        self::assertTrue($response->replayed);
        self::assertSame(['id' => 'addr-1'], $response->data);
    }

    /**
     * The key goes out as an RFC 9651 String, the form the Idempotency-Key
     * draft defines and the site accepts, so a key holding characters a
     * bare token cannot (a colon, a space, a quote, a backslash) arrives as
     * it was given.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function sentKeys(): iterable
    {
        yield 'a colon and spaces' => ['order 42: create article', '"order 42: create article"'];
        yield 'a double quote' => ['say "hi"', '"say \\"hi\\""'];
        yield 'a backslash' => ['C:\\orders\\42', '"C:\\\\orders\\\\42"'];
        yield 'a leading and a trailing space' => [' padded ', '" padded "'];
        yield 'the longest key, every character escaped' => [str_repeat('"', 255), '"' . str_repeat('\\"', 255) . '"'];
    }

    #[DataProvider('sentKeys')]
    public function testACallerSuppliedKeyIsSentAsAStructuredFieldString(string $key, string $header): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1'], 201));

        $response = $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', ['title' => 'T'], idempotencyKey: $key);

        self::assertSame($header, $provider->siteRequests()[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame($key, $response->idempotencyKey);
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
        yield 'a tab' => ["key\tvalue"];
        yield 'a delete character' => ["key\x7F"];
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
            static fn(FakeProvider $provider, RequestInterface $request): never => throw self::networkFailure($request, 'connection reset'),
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
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(409, 'idempotency-key-in-use', headers: ['Retry-After' => '2']),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1'], 201, ['Idempotent-Replayed' => 'true']),
        );

        $response = $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', ['title' => 'T'], idempotencyKey: 'k-1');

        self::assertSame([2.0], $provider->sleeper->sleeps);
        self::assertSame(['"k-1"', '"k-1"'], array_map(static fn(RequestInterface $request): string => $request->getHeaderLine('Idempotency-Key'), $provider->siteRequests()));
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

    /**
     * A gateway that lost or timed out on the site's answer: the request may
     * or may not have run, which a key (or a read) makes safe to repeat.
     *
     * @return iterable<string, array{int}>
     */
    public static function gatewayFailures(): iterable
    {
        yield 'bad gateway' => [502];
        yield 'gateway timeout' => [504];
    }

    #[DataProvider('gatewayFailures')]
    public function testAGatewayFailureIsRetriedForAKeyedWrite(int $status): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->factory->createResponse($status),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json([], 201, ['Idempotent-Replayed' => 'true']),
        );

        $response = $provider->client()->api()->raw()->postIdempotent('/api/v1/articles', [], idempotencyKey: 'k-1');

        self::assertTrue($response->replayed);
        self::assertCount(2, $provider->siteRequests());
        self::assertCount(1, $provider->sleeper->sleeps);
    }

    #[DataProvider('gatewayFailures')]
    public function testAGatewayFailureIsRetriedForARead(int $status): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->factory->createResponse($status),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1']),
        );

        $provider->client()->api()->raw()->get('/api/v1/articles/a-1');

        self::assertCount(2, $provider->siteRequests());
    }

    #[DataProvider('gatewayFailures')]
    public function testAGatewayFailureIsNotRetriedForAnUnkeyedWrite(int $status): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->factory->createResponse($status));

        try {
            $provider->client()->api()->raw()->post('/api/v1/articles', []);
            self::fail('No exception was thrown.');
        } catch (ApiException) {
        }

        self::assertCount(1, $provider->siteRequests());
    }

    /**
     * A read cannot change anything, so a server error on one is worth
     * another try; under a key the same 500 means an outcome only reading
     * the current state can settle, and is thrown (see finalRefusals).
     */
    public function testAServerErrorIsRetriedForARead(): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(500, 'internal'),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1']),
        );

        $provider->client()->api()->raw()->get('/api/v1/articles/a-1');

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
        $provider = self::answering(static fn(FakeProvider $provider, RequestInterface $request): never => throw self::networkFailure($request, 'timed out'));

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
        $provider = self::answering(static fn(FakeProvider $provider, RequestInterface $request): never => throw self::networkFailure($request, 'connection reset'));
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
            static fn(FakeProvider $provider): ResponseInterface => $provider->problem(409, 'idempotency-key-in-use', headers: ['Retry-After' => '2']),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json([], 201),
        );

        $provider->client(logger: $logger)->api()->raw()->postIdempotent('/api/v1/articles', [], idempotencyKey: 'secret-ish-key');

        $retries = $logger->at(LogLevel::INFO);
        self::assertCount(1, $retries);
        self::assertStringContainsString('409', $retries[0]['message']);
        self::assertStringNotContainsString('secret-ish-key', $logger->text());
    }

    public function testAConflictOutsideAKeyedWriteIsThrown(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem(409, 'idempotency-key-in-use', headers: ['Retry-After' => '1']));

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
     * A request the HTTP client refused to send (a malformed one, say) fails
     * the same way on every attempt: only a network failure is retried.
     */
    public function testARequestTheHttpClientRefusesIsNotRetried(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider, RequestInterface $request): never => throw new class ('malformed request', $request) extends RuntimeException implements RequestExceptionInterface {
            public function __construct(string $message, private readonly RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        });

        try {
            $provider->client()->api()->raw()->get('/api/v1/articles/a-1');
            self::fail('No exception was thrown.');
        } catch (TransportException $exception) {
            self::assertFalse($exception->isNetworkFailure());
        }

        self::assertCount(1, $provider->siteRequests());
        self::assertSame([], $provider->sleeper->sleeps);
    }

    /**
     * Guzzle reports a connection reset after the request went out (cURL
     * errors 55 and 56) as a RequestException with no response rather than
     * a ConnectException; cURL failed in transit, so it is retried.
     *
     * @return iterable<string, array{int}>
     */
    public static function curlTransferFailures(): iterable
    {
        yield 'failure sending' => [55];
        yield 'failure receiving' => [56];
        yield 'HTTP/2 stream reset' => [92];
    }

    /**
     * Every other cURL error Guzzle reports without a response fails the
     * same way on the next attempt: a certificate, a CA file, a client
     * certificate, a proxy that does not resolve, a protocol or a URL.
     *
     * @return iterable<string, array{int}>
     */
    public static function curlStandingFailures(): iterable
    {
        yield 'certificate not trusted' => [60];
        yield 'CA file unreadable' => [77];
        yield 'client certificate' => [58];
        yield 'proxy not resolved' => [5];
        yield 'unsupported protocol' => [1];
        yield 'malformed URL' => [3];
    }

    #[DataProvider('curlStandingFailures')]
    public function testAGuzzleFailureThatWouldRecurIsNotRetried(int $errno): void
    {
        $provider = self::answering(static fn(FakeProvider $provider, RequestInterface $request): never => throw new GuzzleRequestException('cURL error ' . $errno, $request, null, null, ['errno' => $errno]));

        try {
            $provider->client()->api()->raw()->get('/api/v1/articles/a-1');
            self::fail('No exception was thrown.');
        } catch (TransportException $exception) {
            self::assertFalse($exception->isNetworkFailure());
        }

        self::assertCount(1, $provider->siteRequests());
    }

    #[DataProvider('curlTransferFailures')]
    public function testAGuzzleTransferFailureWithoutAnAnswerIsRetried(int $errno): void
    {
        $provider = self::answeringInTurn(
            static fn(FakeProvider $provider, RequestInterface $request): never => throw new GuzzleRequestException('cURL error ' . $errno, $request, null, null, ['errno' => $errno]),
            static fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1']),
        );

        $provider->client()->api()->raw()->get('/api/v1/articles/a-1');

        self::assertCount(2, $provider->siteRequests());
        self::assertCount(1, $provider->sleeper->sleeps);
    }

    /**
     * Guzzle refuses some requests itself before cURL runs (a URL without
     * a scheme, a malformed proxy): those carry no cURL error and fail the
     * same way every time.
     */
    public function testARequestGuzzleRefusesBeforeSendingIsNotRetried(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider, RequestInterface $request): never => throw new GuzzleRequestException('The proxy URL is malformed.', $request));

        try {
            $provider->client()->api()->raw()->get('/api/v1/articles/a-1');
            self::fail('No exception was thrown.');
        } catch (TransportException $exception) {
            self::assertFalse($exception->isNetworkFailure());
        }

        self::assertCount(1, $provider->siteRequests());
    }

    public function testAClientFailureThatIsNeitherIsNotRetried(): void
    {
        $provider = self::answering(static fn(): never => throw new class ('something else') extends RuntimeException implements ClientExceptionInterface {});

        try {
            $provider->client()->api()->raw()->get('/api/v1/articles/a-1');
            self::fail('No exception was thrown.');
        } catch (TransportException) {
        }

        self::assertCount(1, $provider->siteRequests());
    }

    public static function networkFailure(RequestInterface $request, string $message): NetworkExceptionInterface
    {
        return new class ($message, $request) extends RuntimeException implements NetworkExceptionInterface {
            public function __construct(string $message, private readonly RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };
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
     * @param \Closure(FakeProvider, RequestInterface): ResponseInterface ...$answers
     */
    private static function answeringInTurn(\Closure ...$answers): FakeProvider
    {
        $provider = new FakeProvider();
        $provider->site = static function (RequestInterface $request) use ($provider, &$answers): ResponseInterface {
            $answer = count($answers) > 1 ? array_shift($answers) : $answers[0];

            return $answer($provider, $request);
        };

        return $provider;
    }
}

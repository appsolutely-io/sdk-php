<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Api\BackendToken;
use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Cache\TokenLock;
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Exception\TransportException;
use Appsolutely\Sdk\Oidc\IdToken;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\RecordingLock;
use Appsolutely\Sdk\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;

/**
 * Asking for the backend's token is safe to repeat, so it is retried like a
 * read; many processes finding the token due at once ask for it once when
 * the integrator supplies a lock; and the token is replaced at a random
 * point shortly before it must be, so those processes do not all find it
 * due at the same moment.
 */
final class BackendTokenTest extends TestCase
{
    private const string TOKEN_URL = FakeProvider::ISSUER . '/oauth/token';

    private FakeProvider $provider;

    private int $issued = 0;

    protected function setUp(): void
    {
        $this->provider = new FakeProvider();
        $this->provider->token = fn(): ResponseInterface => $this->granted();
        $this->provider->site = fn(): ResponseInterface => $this->provider->json(['client_id' => FakeProvider::BACKEND_CLIENT_ID]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function passingFailures(): iterable
    {
        yield 'rate limited' => [429];
        yield 'bad gateway' => [502];
        yield 'unavailable' => [503];
        yield 'gateway timeout' => [504];
    }

    #[DataProvider('passingFailures')]
    public function testAPassingFailureOfTheTokenEndpointIsRetried(int $status): void
    {
        $answers = [$this->provider->json(['error' => 'temporarily_unavailable'], $status, ['Retry-After' => '2'])];
        $this->provider->token = function () use (&$answers): ResponseInterface {
            return array_shift($answers) ?? $this->granted();
        };

        $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');

        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame([2.0], $this->provider->sleeper->sleeps);
        self::assertSame('Bearer machine-1', $this->provider->siteRequests()[0]->getHeaderLine('Authorization'));
    }

    public function testANetworkFailureOfTheTokenEndpointIsRetried(): void
    {
        $failed = false;
        $this->provider->token = function (RequestInterface $request) use (&$failed): ResponseInterface {
            if (!$failed) {
                $failed = true;

                throw RetryTest::networkFailure($request, 'connection reset');
            }

            return $this->granted();
        };

        $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');

        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertCount(1, $this->provider->sleeper->sleeps);
    }

    public function testARefusedCredentialIsNotRetried(): void
    {
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['error' => 'invalid_client'], 401);

        try {
            $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');
            self::fail('No exception was thrown.');
        } catch (OAuthException) {
        }

        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame([], $this->provider->sleeper->sleeps);
    }

    public function testRetriesOfTheTokenEndpointStopAtThePolicysLimit(): void
    {
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['error' => 'temporarily_unavailable'], 503);

        try {
            $this->provider->backendClient(retryPolicy: new RetryPolicy(maxRetries: 3, sleeper: $this->provider->sleeper))->backend()->raw()->get('/api/v1/client');
            self::fail('No exception was thrown.');
        } catch (OAuthException $exception) {
            self::assertSame(503, $exception->statusCode);
        }

        self::assertCount(4, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertCount(3, $this->provider->sleeper->sleeps);
    }

    public function testAMemberSignInIsNeverRetried(): void
    {
        $this->provider->token = static fn(RequestInterface $request): never => throw RetryTest::networkFailure($request, 'connection reset');

        $this->expectException(TransportException::class);

        try {
            $this->provider->oidc()->refresh('refresh-token', new IdToken('raw', 'member-42', ['sub' => 'member-42']));
        } finally {
            self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
        }
    }

    /**
     * Another process fetched the token while this one waited for the
     * lock: this one takes it from the cache instead of asking again.
     */
    public function testATokenFetchedByAnotherProcessWhileWaitingForTheLockIsUsed(): void
    {
        $lock = new RecordingLock(function (): void {
            $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');
        });
        $client = $this->clientWithLock($lock);

        $client->backend()->raw()->get('/api/v1/client');

        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame(['Bearer machine-1', 'Bearer machine-1'], array_map(static fn(RequestInterface $request): string => $request->getHeaderLine('Authorization'), $this->provider->siteRequests()));
        self::assertCount(1, $lock->runs);
        self::assertStringStartsWith('appsolutely.backend.token.', $lock->runs[0]['name']);
        self::assertGreaterThan(0, $lock->runs[0]['seconds']);
    }

    public function testATokenInTheCacheIsUsedWithoutTakingTheLock(): void
    {
        $lock = new RecordingLock();
        $client = $this->clientWithLock($lock);

        $client->backend()->raw()->get('/api/v1/client');
        $client->backend()->raw()->get('/api/v1/client');

        self::assertCount(1, $lock->runs);
        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
    }

    public function testATokenIsNotReplacedBeforeItsRefreshWindow(): void
    {
        $token = $this->backendToken(random: 0.0);

        self::assertSame('machine-1', $token->token());
        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN - BackendToken::REFRESH_WINDOW - 1);

        self::assertSame('machine-1', $token->token());
        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
    }

    public function testATokenIsReplacedEarlyAtTheRandomPointDrawnInItsWindow(): void
    {
        $token = $this->backendToken(random: 0.5);
        $token->token();
        $windowStart = 3600 - BackendToken::EXPIRY_MARGIN - BackendToken::REFRESH_WINDOW;

        $this->provider->clock->advance($windowStart + BackendToken::REFRESH_WINDOW / 2 - 1);
        self::assertSame('machine-1', $token->token());

        $this->provider->clock->advance(1);
        self::assertSame('machine-2', $token->token());
        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
    }

    /**
     * Within the window the kept token is still good: a failure to replace
     * it early is logged and the kept one used.
     */
    public function testAFailedEarlyReplacementKeepsTheTokenStillGood(): void
    {
        $logger = new RecordingLogger();
        $token = $this->backendToken(random: 0.0, logger: $logger);
        $token->token();
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['error' => 'invalid_client'], 401);

        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN - 1);

        self::assertSame('machine-1', $token->token());
        self::assertCount(1, $logger->at(LogLevel::WARNING));
    }

    /**
     * A call in the window holds a token still good, so it never waits:
     * when another process holds the lock, the kept token is sent and the
     * holder left to replace it.
     */
    public function testAnEarlyReplacementDoesNotWaitForTheLock(): void
    {
        $lock = new RecordingLock();
        $token = $this->backendToken(random: 0.0, lock: $lock);
        $token->token();
        $lock->held = true;

        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN - 1);

        self::assertSame('machine-1', $token->token());
        self::assertCount(1, $lock->runs);
        self::assertCount(1, $lock->tries);
        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
    }

    public function testAnEarlyReplacementTakesAFreeLockWithoutWaiting(): void
    {
        $lock = new RecordingLock();
        $token = $this->backendToken(random: 0.0, lock: $lock);
        $token->token();

        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN - 1);

        self::assertSame('machine-2', $token->token());
        self::assertCount(1, $lock->runs);
        self::assertCount(1, $lock->tries);
        self::assertStringStartsWith('appsolutely.backend.token.', $lock->tries[0]['name']);
    }

    /**
     * Retrying would hold up a call whose token is still good: an early
     * replacement asks once and keeps the current token when that fails.
     */
    public function testAnEarlyReplacementIsNotRetried(): void
    {
        $token = $this->backendToken(random: 0.0);
        $token->token();
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['error' => 'temporarily_unavailable'], 503);

        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN - 1);

        self::assertSame('machine-1', $token->token());
        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame([], $this->provider->sleeper->sleeps);
    }

    public function testAnEarlyReplacementWhoseLockFailsKeepsTheTokenAndLogsOnlyTheClass(): void
    {
        $logger = new RecordingLogger();
        $lock = new class implements TokenLock {
            public function run(string $name, int $seconds, callable $fetch): mixed
            {
                return $fetch();
            }

            public function runIfFree(string $name, int $seconds, callable $fetch): ?string
            {
                throw new \RuntimeException('redis://user:secret@cache.internal refused');
            }
        };
        $token = $this->backendToken(random: 0.0, logger: $logger, lock: $lock);
        $token->token();

        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN - 1);

        self::assertSame('machine-1', $token->token());
        self::assertCount(1, $logger->at(LogLevel::INFO));
        self::assertStringContainsString('RuntimeException', $logger->at(LogLevel::INFO)[0]['message']);
        self::assertStringNotContainsString('secret', $logger->at(LogLevel::INFO)[0]['message']);
    }

    /**
     * Past its margin there is no token to fall back on, so the caller
     * waits for the lock and the request is retried.
     */
    public function testAReplacementPastTheMarginWaitsForTheLockAndIsRetried(): void
    {
        $lock = new RecordingLock();
        $token = $this->backendToken(random: 0.0, lock: $lock);
        $token->token();
        $answers = [$this->provider->json(['error' => 'temporarily_unavailable'], 503)];
        $this->provider->token = function () use (&$answers): ResponseInterface {
            return array_shift($answers) ?? $this->granted();
        };

        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN);

        self::assertSame('machine-2', $token->token());
        self::assertCount(2, $lock->runs);
        self::assertSame([], $lock->tries);
        self::assertCount(1, $this->provider->sleeper->sleeps);
    }

    public function testATokenPastItsMarginIsNeverUsed(): void
    {
        $token = $this->backendToken(random: 1.0);
        $token->token();
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['error' => 'invalid_client'], 401);

        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN);

        $this->expectException(OAuthException::class);
        $token->token();
    }

    private function granted(): ResponseInterface
    {
        return $this->provider->json(['access_token' => 'machine-' . ++$this->issued, 'token_type' => 'Bearer', 'expires_in' => 3600]);
    }

    private function backendToken(float $random, ?RecordingLogger $logger = null, ?TokenLock $lock = null): BackendToken
    {
        return new BackendToken(
            $this->provider->oidc(),
            $this->provider->cache,
            $this->provider->clock,
            FakeProvider::ISSUER,
            FakeProvider::BACKEND_CLIENT_ID,
            FakeProvider::BACKEND_CLIENT_SECRET,
            [],
            lock: $lock,
            logger: $logger,
            random: static fn(): float => $random,
        );
    }

    private function clientWithLock(TokenLock $lock): Client
    {
        return new Client(new Config(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            clientId: FakeProvider::CLIENT_ID,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $this->provider->http,
            requestFactory: $this->provider->factory,
            streamFactory: $this->provider->factory,
            cache: $this->provider->cache,
            clock: $this->provider->clock,
            retryPolicy: new RetryPolicy(sleeper: $this->provider->sleeper),
            backendClientId: FakeProvider::BACKEND_CLIENT_ID,
            backendClientSecret: FakeProvider::BACKEND_CLIENT_SECRET,
            tokenLock: $lock,
        ));
    }
}

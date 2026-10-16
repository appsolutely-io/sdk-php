<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Exception\ServiceUnavailableException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;

/**
 * Each attempt is logged at debug with what identifies it (method, path,
 * status, request id, attempt), giving up at warning; nothing that may hold
 * a secret (headers, bodies, query strings, tokens, keys) is logged.
 */
final class LoggingTest extends TestCase
{
    private const string SECRET = 'sk-live-0f9e8d7c6b5a';

    public function testEachAttemptIsLoggedAtDebugWithWhatIdentifiesIt(): void
    {
        $logger = new RecordingLogger();
        $provider = new FakeProvider();
        $provider->site = static fn(): ResponseInterface => $provider->json(['id' => 'a-1']);

        $provider->client(logger: $logger)->api()->raw()->get('/api/v1/articles/a-1', requestId: 'req-7');

        $debug = $logger->at(LogLevel::DEBUG);
        self::assertCount(1, $debug);
        self::assertSame(['method' => 'GET', 'path' => '/api/v1/articles/a-1', 'status' => 200, 'request_id' => 'req-7', 'attempt' => 1], $debug[0]['context']);
        self::assertStringContainsString('GET /api/v1/articles/a-1', $debug[0]['message']);
    }

    public function testGivingUpAfterRetriesIsAWarning(): void
    {
        $logger = new RecordingLogger();
        $provider = new FakeProvider();
        $provider->site = static fn(): ResponseInterface => $provider->problem(503, 'temporarily-unavailable');

        try {
            $provider->client(retryPolicy: new RetryPolicy(maxRetries: 1, sleeper: $provider->sleeper), logger: $logger)->api()->raw()->get('/api/v1/articles/a-1', requestId: 'req-8');
            self::fail('No exception was thrown.');
        } catch (ServiceUnavailableException) {
        }

        self::assertCount(2, $logger->at(LogLevel::DEBUG));
        $warnings = $logger->at(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertSame(503, $warnings[0]['context']['status'] ?? null);
        self::assertSame(2, $warnings[0]['context']['attempt'] ?? null);
        self::assertSame('req-8', $warnings[0]['context']['request_id'] ?? null);
    }

    /**
     * A refusal is the site's answer, logged at debug; it is the caller's
     * to report.
     */
    public function testARefusalARetryCannotChangeIsNotAWarning(): void
    {
        $logger = new RecordingLogger();
        $provider = new FakeProvider();
        $provider->site = static fn(): ResponseInterface => $provider->problem(404, 'not-found');

        try {
            $provider->client(logger: $logger)->api()->raw()->get('/api/v1/articles/a-1');
            self::fail('No exception was thrown.');
        } catch (NotFoundException) {
        }

        self::assertSame([], $logger->at(LogLevel::WARNING));
        self::assertCount(1, $logger->at(LogLevel::DEBUG));
    }

    public function testNoSecretInAHeaderABodyOrAQueryReachesTheLog(): void
    {
        $logger = new RecordingLogger();
        $provider = new FakeProvider();
        $provider->site = static fn(): ResponseInterface => $provider->problem(503, 'temporarily-unavailable', ['detail' => self::SECRET]);
        $api = $provider->client(logger: $logger)->forMember(self::SECRET)->api()->raw();

        foreach ([
            static fn() => $api->get('/api/v1/articles', ['search' => self::SECRET]),
            static fn() => $api->postIdempotent('/api/v1/articles', ['title' => self::SECRET], idempotencyKey: self::SECRET),
            static fn() => $api->post('/api/v1/articles', ['content' => self::SECRET]),
        ] as $call) {
            try {
                $call();
                self::fail('No exception was thrown.');
            } catch (ServiceUnavailableException) {
            }
        }

        self::assertNotSame([], $logger->at(LogLevel::WARNING));
        self::assertStringNotContainsString(self::SECRET, $logger->text());
    }

    public function testAFailedBackendTokenRequestIsAWarningWithoutTheSecret(): void
    {
        $logger = new RecordingLogger();
        $provider = new FakeProvider();
        $provider->token = static fn(): ResponseInterface => $provider->json(['error' => 'invalid_client', 'error_description' => 'bad'], 401);
        $client = new \Appsolutely\Sdk\Client(new \Appsolutely\Sdk\Config(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            clientId: FakeProvider::CLIENT_ID,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $provider->http,
            requestFactory: $provider->factory,
            streamFactory: $provider->factory,
            cache: $provider->cache,
            clock: $provider->clock,
            logger: $logger,
            retryPolicy: new RetryPolicy(sleeper: $provider->sleeper),
            backendClientId: FakeProvider::BACKEND_CLIENT_ID,
            backendClientSecret: FakeProvider::BACKEND_CLIENT_SECRET,
        ));

        try {
            $client->backend()->raw()->get('/api/v1/client');
            self::fail('No exception was thrown.');
        } catch (OAuthException) {
        }

        $warnings = $logger->at(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertStringContainsString('backend token', $warnings[0]['message']);
        self::assertStringNotContainsString(FakeProvider::BACKEND_CLIENT_SECRET, $logger->text());
        self::assertStringNotContainsString(urlencode(FakeProvider::BACKEND_CLIENT_SECRET), $logger->text());
    }
}

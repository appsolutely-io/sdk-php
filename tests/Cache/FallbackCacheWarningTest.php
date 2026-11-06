<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Cache;

use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Without a shared cache the client still works, but under PHP-FPM every
 * request fetches discovery, the signing keys and the backend's token again;
 * that is said once, when the client is built, rather than left to be
 * found in a latency graph.
 */
final class FallbackCacheWarningTest extends TestCase
{
    public function testAClientWithoutACacheWarnsOnce(): void
    {
        $logger = new RecordingLogger();
        $provider = new FakeProvider();

        new Client(self::config($provider, $logger, withCache: false));

        $warnings = $logger->at(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertStringContainsString('PSR-16', $warnings[0]['message']);
    }

    public function testAClientWithACacheSaysNothing(): void
    {
        $logger = new RecordingLogger();

        new Client(self::config(new FakeProvider(), $logger, withCache: true));

        self::assertSame([], $logger->records);
    }

    private static function config(FakeProvider $provider, RecordingLogger $logger, bool $withCache): Config
    {
        return new Config(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            clientId: FakeProvider::CLIENT_ID,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $provider->http,
            requestFactory: $provider->factory,
            streamFactory: $provider->factory,
            cache: $withCache ? $provider->cache : null,
            logger: $logger,
        );
    }
}

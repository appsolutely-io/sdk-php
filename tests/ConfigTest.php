<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\AppsolutelyException;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testItKeepsWhatItWasGivenVerbatim(): void
    {
        $config = new Config(
            baseUrl: 'https://site.example.com',
            issuer: 'https://login.example.com',
            clientId: 'client-1',
            clientSecret: 'secret-1',
            apiToken: '7|admin-token',
        );

        self::assertSame('https://site.example.com', $config->baseUrl);
        self::assertSame('https://login.example.com', $config->issuer);
        self::assertSame('7|admin-token', $config->apiToken());
        self::assertSame('client-1', $config->clientId);
        self::assertSame('secret-1', $config->clientSecret());
        self::assertNull($config->httpClient);
        self::assertNull($config->cache);
        self::assertNull($config->clock);
        self::assertNull($config->logger);
    }

    public function testTheAdministratorTokenIsOptional(): void
    {
        self::assertNull((new Config('https://site.example.com', 'https://login.example.com', 'id', 'secret'))->apiToken());
    }

    public function testABaseUrlWithOnlyATrailingSlashIsAccepted(): void
    {
        self::assertSame('https://site.example.com/', (new Config('https://site.example.com/', 'https://login.example.com', 'id', 'secret'))->baseUrl);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function secretProperties(): iterable
    {
        yield 'client secret' => ['clientSecret'];
        yield 'administrator token' => ['apiToken'];
    }

    #[DataProvider('secretProperties')]
    public function testASecretIsNotAPublicProperty(string $name): void
    {
        $property = new \ReflectionProperty(Config::class, $name);

        self::assertFalse($property->isPublic());
    }

    public function testDumpingTheConfigOrTheClientsBuiltOnItHidesTheSecrets(): void
    {
        $provider = new FakeProvider();
        $config = $provider->config();
        $client = new Client($config);

        foreach ([$config, $client, $client->oidc()] as $object) {
            ob_start();
            var_dump($object);
            $dumped = (string) ob_get_clean();

            foreach ([FakeProvider::CLIENT_SECRET, FakeProvider::API_TOKEN] as $secret) {
                self::assertStringNotContainsString($secret, $dumped);
                self::assertStringNotContainsString($secret, print_r($object, true));
            }
        }
        self::assertStringContainsString('[redacted]', print_r($config, true));
    }

    /**
     * A serialized copy would carry the secret into a session, a cache or a
     * queue payload, and the live services it holds do not serialize at all.
     */
    public function testTheConfigAndTheClientsBuiltOnItRefuseToBeSerialized(): void
    {
        $client = new Client((new FakeProvider())->config());

        foreach ([$client->oidc(), $client] as $object) {
            try {
                serialize($object);
                self::fail(get_debug_type($object) . ' was serialized.');
            } catch (NotSerializableException $exception) {
                self::assertStringNotContainsString(FakeProvider::CLIENT_SECRET, $exception->getMessage());
            }
        }

        $this->expectException(NotSerializableException::class);

        serialize((new FakeProvider())->config());
    }

    public function testASerializedConfigIsNotRestored(): void
    {
        $this->expectException(NotSerializableException::class);

        unserialize('O:22:"Appsolutely\\Sdk\\Config":0:{}');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function loopbackIssuers(): iterable
    {
        yield 'localhost' => ['http://localhost:8000'];
        yield 'IPv4 loopback' => ['http://127.0.0.1:8000'];
        yield 'IPv6 loopback' => ['http://[::1]:8000'];
    }

    #[DataProvider('loopbackIssuers')]
    public function testPlainHttpIsAcceptedForALoopbackHost(string $issuer): void
    {
        self::assertSame($issuer, (new Config($issuer, $issuer, 'id', 'secret'))->issuer);
        self::assertSame($issuer, (new Config($issuer, $issuer, 'id', 'secret'))->baseUrl);
    }

    /**
     * URLs that parsers read differently: PHP's parse_url() takes the host of
     * `http://evil.com\@localhost` to be `localhost`, while a browser or
     * another HTTP client takes it to be `evil.com`. None of them is a URL a
     * provider has a reason to publish, so they are refused outright.
     *
     * @return iterable<string, array{string}>
     */
    public static function ambiguousIssuers(): iterable
    {
        yield 'backslash before user info on the loopback' => ['http://evil.com\\@localhost'];
        yield 'backslash before user info' => ['https://evil.com\\@login.example.com'];
        yield 'backslash in the path' => ['https://login.example.com\\path'];
        yield 'user info' => ['https://a@login.example.com'];
        yield 'user info with a password' => ['https://a:b@login.example.com'];
        yield 'an empty user info' => ['https://@login.example.com'];
        yield 'a line break' => ["https://login.example.com/\nFORGED"];
        yield 'a NUL byte' => ["https://login.example.com/\0"];
        yield 'a tab' => ["https://login.example.com/\tx"];
        yield 'DEL' => ["https://login.example.com/\x7F"];
        yield 'a space' => ['https://login.example.com/a b'];
    }

    #[DataProvider('ambiguousIssuers')]
    public function testAnIssuerThatParsersReadDifferentlyIsRefused(string $issuer): void
    {
        try {
            new Config('https://site.example.com', $issuer, 'id', 'secret');
            self::fail('Config accepted an ambiguous issuer.');
        } catch (InvalidConfigException $exception) {
            self::assertStringContainsString('The issuer', $exception->getMessage());
            self::assertStringContainsString('user info', $exception->getMessage());
            self::assertDoesNotMatchRegularExpression('/[^\x20-\x7E]/', $exception->getMessage());
        }
    }

    /**
     * The base URL receives the administrator token on every call, so it is
     * held to the issuer's rules.
     */
    #[DataProvider('ambiguousIssuers')]
    public function testABaseUrlThatParsersReadDifferentlyIsRefused(string $baseUrl): void
    {
        try {
            new Config($baseUrl, 'https://login.example.com', 'id', 'secret');
            self::fail('Config accepted an ambiguous base URL.');
        } catch (InvalidConfigException $exception) {
            self::assertStringContainsString('The base URL', $exception->getMessage());
            self::assertDoesNotMatchRegularExpression('/[^\x20-\x7E]/', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function acceptedLeeways(): iterable
    {
        yield 'none' => [0];
        yield 'five minutes' => [300];
    }

    #[DataProvider('acceptedLeeways')]
    public function testAClockLeewayFromZeroToFiveMinutesIsAccepted(int $leeway): void
    {
        self::assertSame($leeway, (new Config('https://site.example.com', 'https://login.example.com', 'id', 'secret', clockLeeway: $leeway))->clockLeeway);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLeeways(): iterable
    {
        yield 'negative' => [-1];
        yield 'beyond five minutes' => [301];
    }

    #[DataProvider('refusedLeeways')]
    public function testAClockLeewayOutsideZeroToFiveMinutesIsRefused(int $leeway): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('clock leeway');

        new Config('https://site.example.com', 'https://login.example.com', 'id', 'secret', clockLeeway: $leeway);
    }

    /**
     * @return iterable<string, array{string, string, string, string, ?string}>
     */
    public static function invalid(): iterable
    {
        yield 'base URL over plain http' => ['http://site.example.com', 'https://login.example.com', 'id', 'secret', null];
        yield 'base URL without a host' => ['https://', 'https://login.example.com', 'id', 'secret', null];
        yield 'base URL that is not a URL' => ['site.example.com', 'https://login.example.com', 'id', 'secret', null];
        yield 'base URL with a query' => ['https://site.example.com?x=1', 'https://login.example.com', 'id', 'secret', null];
        yield 'base URL with a fragment' => ['https://site.example.com#x', 'https://login.example.com', 'id', 'secret', null];
        // Every path the client calls is absolute from the site's origin.
        yield 'base URL with a path' => ['https://site.example.com/shop', 'https://login.example.com', 'id', 'secret', null];
        yield 'empty administrator token' => ['https://site.example.com', 'https://login.example.com', 'id', 'secret', ''];
        yield 'administrator token with a line break' => ['https://site.example.com', 'https://login.example.com', 'id', 'secret', "token\r\nX-Forged: 1"];
        yield 'administrator token with a space' => ['https://site.example.com', 'https://login.example.com', 'id', 'secret', 'two words'];
        yield 'administrator token beyond ASCII' => ['https://site.example.com', 'https://login.example.com', 'id', 'secret', "t\u{00E9}"];
        yield 'issuer over plain http' => ['https://site.example.com', 'http://login.example.com', 'id', 'secret', null];
        // Whether a *.localhost name resolves to the loopback interface is up
        // to the resolver, so only the three exact loopback forms are trusted.
        yield 'issuer over plain http on a localhost subdomain' => ['https://site.example.com', 'http://login.localhost', 'id', 'secret', null];
        yield 'issuer over plain http on another 127/8 address' => ['https://site.example.com', 'http://127.0.0.2', 'id', 'secret', null];
        yield 'issuer without a host' => ['https://site.example.com', 'https://', 'id', 'secret', null];
        yield 'issuer that is not a URL' => ['https://site.example.com', 'login.example.com', 'id', 'secret', null];
        yield 'issuer with a query' => ['https://site.example.com', 'https://login.example.com?x=1', 'id', 'secret', null];
        yield 'issuer with a fragment' => ['https://site.example.com', 'https://login.example.com#x', 'id', 'secret', null];
        yield 'empty client id' => ['https://site.example.com', 'https://login.example.com', '', 'secret', null];
        yield 'empty client secret' => ['https://site.example.com', 'https://login.example.com', 'id', '', null];
    }

    #[DataProvider('invalid')]
    public function testItRefusesAnInvalidValueAtConstruction(string $baseUrl, string $issuer, string $clientId, string $clientSecret, ?string $apiToken): void
    {
        try {
            new Config($baseUrl, $issuer, $clientId, $clientSecret, $apiToken);
            self::fail('Config accepted an invalid value.');
        } catch (InvalidConfigException $exception) {
            self::assertInstanceOf(AppsolutelyException::class, $exception);
        }
    }
}

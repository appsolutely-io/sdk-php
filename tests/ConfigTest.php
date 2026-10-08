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
            issuer: 'https://login.example.com',
            clientId: 'client-1',
            clientSecret: 'secret-1',
        );

        self::assertSame('https://login.example.com', $config->issuer);
        self::assertSame('client-1', $config->clientId);
        self::assertSame('secret-1', $config->clientSecret());
        self::assertNull($config->httpClient);
        self::assertNull($config->cache);
        self::assertNull($config->clock);
        self::assertNull($config->logger);
    }

    public function testTheSecretIsNotAPublicProperty(): void
    {
        $property = new \ReflectionProperty(Config::class, 'clientSecret');

        self::assertFalse($property->isPublic());
    }

    public function testDumpingTheConfigOrTheClientsBuiltOnItHidesTheSecret(): void
    {
        $provider = new FakeProvider();
        $config = $provider->config();
        $client = new Client($config);

        foreach ([$config, $client, $client->oidc()] as $object) {
            ob_start();
            var_dump($object);
            $dumped = (string) ob_get_clean();

            self::assertStringNotContainsString(FakeProvider::CLIENT_SECRET, $dumped);
            self::assertStringNotContainsString(FakeProvider::CLIENT_SECRET, print_r($object, true));
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
        self::assertSame($issuer, (new Config($issuer, 'id', 'secret'))->issuer);
    }

    public function testAClockLeewayBeyondFiveMinutesIsRefused(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Config('https://login.example.com', 'id', 'secret', clockLeeway: 301);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'issuer over plain http' => ['http://login.example.com', 'id', 'secret'];
        // Whether a *.localhost name resolves to the loopback interface is up
        // to the resolver, so only the three exact loopback forms are trusted.
        yield 'issuer over plain http on a localhost subdomain' => ['http://login.localhost', 'id', 'secret'];
        yield 'issuer over plain http on another 127/8 address' => ['http://127.0.0.2', 'id', 'secret'];
        yield 'issuer without a host' => ['https://', 'id', 'secret'];
        yield 'issuer that is not a URL' => ['login.example.com', 'id', 'secret'];
        yield 'issuer with a query' => ['https://login.example.com?x=1', 'id', 'secret'];
        yield 'issuer with a fragment' => ['https://login.example.com#x', 'id', 'secret'];
        yield 'empty client id' => ['https://login.example.com', '', 'secret'];
        yield 'empty client secret' => ['https://login.example.com', 'id', ''];
    }

    #[DataProvider('invalid')]
    public function testItRefusesAnInvalidValueAtConstruction(string $issuer, string $clientId, string $clientSecret): void
    {
        try {
            new Config($issuer, $clientId, $clientSecret);
            self::fail('Config accepted an invalid value.');
        } catch (InvalidConfigException $exception) {
            self::assertInstanceOf(AppsolutelyException::class, $exception);
        }
    }
}

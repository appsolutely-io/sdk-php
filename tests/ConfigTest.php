<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\AppsolutelyException;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testItKeepsWhatItWasGivenVerbatim(): void
    {
        $config = new Config(
            issuer: 'https://login.example.com',
            apiBaseUrl: 'https://api.example.com/api/relying-party/v1',
            clientId: 'client-1',
            clientSecret: 'secret-1',
        );

        self::assertSame('https://login.example.com', $config->issuer);
        self::assertSame('https://api.example.com/api/relying-party/v1', $config->apiBaseUrl);
        self::assertSame('client-1', $config->clientId);
        self::assertSame('secret-1', $config->clientSecret);
        self::assertNull($config->httpClient);
        self::assertNull($config->cache);
        self::assertNull($config->clock);
        self::assertNull($config->logger);
    }

    public function testPlainHttpIsAcceptedForALoopbackHostOnly(): void
    {
        $config = new Config('http://localhost:8000', 'http://127.0.0.1:8000/api', 'id', 'secret');

        self::assertSame('http://localhost:8000', $config->issuer);
    }

    public function testAClockLeewayBeyondFiveMinutesIsRefused(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Config('https://login.example.com', 'https://api.example.com', 'id', 'secret', clockLeeway: 301);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'issuer over plain http' => ['http://login.example.com', 'https://api.example.com', 'id', 'secret'];
        yield 'issuer without a host' => ['https://', 'https://api.example.com', 'id', 'secret'];
        yield 'issuer that is not a URL' => ['login.example.com', 'https://api.example.com', 'id', 'secret'];
        yield 'issuer with a query' => ['https://login.example.com?x=1', 'https://api.example.com', 'id', 'secret'];
        yield 'issuer with a fragment' => ['https://login.example.com#x', 'https://api.example.com', 'id', 'secret'];
        yield 'api base over plain http' => ['https://login.example.com', 'http://api.example.com', 'id', 'secret'];
        yield 'empty client id' => ['https://login.example.com', 'https://api.example.com', '', 'secret'];
        yield 'empty client secret' => ['https://login.example.com', 'https://api.example.com', 'id', ''];
    }

    #[DataProvider('invalid')]
    public function testItRefusesAnInvalidValueAtConstruction(string $issuer, string $apiBaseUrl, string $clientId, string $clientSecret): void
    {
        try {
            new Config($issuer, $apiBaseUrl, $clientId, $clientSecret);
            self::fail('Config accepted an invalid value.');
        } catch (InvalidConfigException $exception) {
            self::assertInstanceOf(AppsolutelyException::class, $exception);
        }
    }
}

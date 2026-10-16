<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * The member sign-in client is needed only for signing members in: an
 * integration that calls as the site's backend, or makes only the public
 * calls, configures neither it nor, without a backend, an issuer, and is
 * refused only when it reaches for sign-in.
 */
final class MemberSignInOptionalTest extends TestCase
{
    public function testABackendOnlyConfigCallsAsTheBackendAndRefusesSignIn(): void
    {
        $provider = new FakeProvider();
        $provider->token = static fn(): ResponseInterface => $provider->json(['access_token' => 'machine-1', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        $provider->site = static fn(): ResponseInterface => $provider->json(['client_id' => FakeProvider::BACKEND_CLIENT_ID]);
        $client = new Client(new Config(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            httpClient: $provider->http,
            requestFactory: $provider->factory,
            streamFactory: $provider->factory,
            cache: $provider->cache,
            clock: $provider->clock,
            backendClientId: FakeProvider::BACKEND_CLIENT_ID,
            backendClientSecret: FakeProvider::BACKEND_CLIENT_SECRET,
        ));

        $client->backend()->raw()->get('/api/v1/client');
        self::assertSame('Bearer machine-1', $provider->siteRequests()[0]->getHeaderLine('Authorization'));

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('member sign-in');
        $client->oidc();
    }

    public function testAConfigForThePublicCallsAloneNeedsNoIssuer(): void
    {
        $provider = new FakeProvider();
        $provider->site = static fn(): ResponseInterface => $provider->json(['data' => [], 'next_cursor' => null]);
        $config = new Config(
            baseUrl: FakeProvider::BASE_URL,
            httpClient: $provider->http,
            requestFactory: $provider->factory,
            streamFactory: $provider->factory,
            cache: $provider->cache,
        );
        $client = new Client($config);

        $client->api()->raw()->get('/api/v1');

        self::assertNull($config->issuer);
        self::assertNull($config->clientId);
        self::assertNull($config->clientSecret());
        self::assertFalse($provider->siteRequests()[0]->hasHeader('Authorization'));
        $this->expectException(InvalidConfigException::class);
        $client->oidc();
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function incomplete(): iterable
    {
        yield 'a client id without its secret' => [['issuer' => FakeProvider::ISSUER, 'clientId' => 'id']];
        yield 'a client secret without its id' => [['issuer' => FakeProvider::ISSUER, 'clientSecret' => 'secret']];
        yield 'a member client without an issuer' => [['clientId' => 'id', 'clientSecret' => 'secret']];
        yield 'a backend client without an issuer' => [['backendClientId' => 'b', 'backendClientSecret' => 'secret']];
    }

    /**
     * @param array<string, string> $settings
     */
    #[DataProvider('incomplete')]
    public function testAnIncompleteSettingIsRefusedAtConstruction(array $settings): void
    {
        $this->expectException(InvalidConfigException::class);

        new Config(FakeProvider::BASE_URL, ...$settings);
    }
}

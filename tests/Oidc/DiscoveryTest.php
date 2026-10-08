<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Oidc\ProviderMetadata;
use Appsolutely\Sdk\Sdk;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase
{
    private const string URL = FakeProvider::ISSUER . '/.well-known/openid-configuration';

    public function testItReadsTheDocumentAtTheWellKnownPathOfTheIssuer(): void
    {
        $provider = new FakeProvider();

        $metadata = $provider->oidc()->metadata();

        self::assertSame(FakeProvider::ISSUER, $metadata->issuer);
        self::assertSame(FakeProvider::ISSUER . '/oauth/token', $metadata->tokenEndpoint);
        self::assertSame(FakeProvider::ISSUER . '/oauth/jwks.json', $metadata->jwksUri);
        self::assertSame(['RS256', 'ES256'], $metadata->idTokenSigningAlgValuesSupported);
        self::assertTrue($metadata->authorizationResponseIssParameterSupported);
        self::assertCount(1, $provider->requestsTo('GET', self::URL));
    }

    public function testItKeepsTheDocumentForItsCacheControlMaxAge(): void
    {
        $provider = new FakeProvider();
        $provider->discoveryHeaders = ['Cache-Control' => 'public, max-age=120'];

        $provider->oidc()->metadata();
        $provider->clock->advance(119);
        $provider->oidc()->metadata();
        self::assertCount(1, $provider->requestsTo('GET', self::URL));

        $provider->clock->advance(2);
        $provider->oidc()->metadata();
        self::assertCount(2, $provider->requestsTo('GET', self::URL));
    }

    public function testItFallsBackToAnHourWhenTheResponseNamesNoMaxAge(): void
    {
        $provider = new FakeProvider();
        $provider->discoveryHeaders = ['Cache-Control' => 'no-cache, private'];

        $provider->oidc()->metadata();
        $provider->clock->advance(3599);
        $provider->oidc()->metadata();
        self::assertCount(1, $provider->requestsTo('GET', self::URL));

        $provider->clock->advance(2);
        $provider->oidc()->metadata();
        self::assertCount(2, $provider->requestsTo('GET', self::URL));
    }

    public function testItDoesNotStoreADocumentMarkedNoStore(): void
    {
        $provider = new FakeProvider();
        $provider->discoveryHeaders = ['Cache-Control' => 'no-store'];

        $provider->oidc()->metadata();
        $provider->oidc()->metadata();

        self::assertCount(2, $provider->requestsTo('GET', self::URL));
    }

    public function testItRefusesADocumentWhoseIssuerDiffersFromTheConfiguredOne(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['issuer'] = FakeProvider::ISSUER . '/';

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('issuer');

        $provider->oidc()->metadata();
    }

    public function testItRefusesADocumentMissingARequiredEndpoint(): void
    {
        $provider = new FakeProvider();
        unset($provider->discovery['jwks_uri']);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('jwks_uri');

        $provider->oidc()->metadata();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function endpoints(): iterable
    {
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'userinfo_endpoint', 'revocation_endpoint'] as $name) {
            yield $name => [$name];
        }
    }

    /**
     * Each of them carries a secret or decides which keys are trusted, so it
     * is held to the issuer's rule: TLS unless the host is the loopback.
     */
    #[DataProvider('endpoints')]
    public function testItRefusesAnEndpointOverPlainHttpOnARemoteHost(string $name): void
    {
        $provider = new FakeProvider();
        $provider->discovery[$name] = 'http://login.example.com/oauth/x';

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage($name);

        $provider->oidc()->metadata();
    }

    #[DataProvider('endpoints')]
    public function testItRefusesAnEndpointThatIsNotAnAbsoluteUrl(string $name): void
    {
        $provider = new FakeProvider();
        $provider->discovery[$name] = '/oauth/x';

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage($name);

        $provider->oidc()->metadata();
    }

    public function testItAcceptsEndpointsOverPlainHttpOnEachLoopbackForm(): void
    {
        $document = [
            'issuer' => 'http://localhost:8000',
            'authorization_endpoint' => 'http://localhost:8000/oauth/authorize',
            'token_endpoint' => 'http://127.0.0.1:8000/oauth/token',
            'jwks_uri' => 'http://[::1]:8000/oauth/jwks.json',
            'userinfo_endpoint' => 'http://localhost/oauth/userinfo',
            'revocation_endpoint' => 'http://127.0.0.1/oauth/revoke',
            'id_token_signing_alg_values_supported' => ['RS256'],
        ];

        $metadata = ProviderMetadata::fromArray($document);

        self::assertSame('http://[::1]:8000/oauth/jwks.json', $metadata->jwksUri);
    }

    public function testItRefusesAnAnswerThatIsNotADiscoveryDocument(): void
    {
        $provider = new FakeProvider();
        $provider->discoveryHeaders = [];
        $provider->discovery = ['error' => 'nope'];

        $this->expectException(DiscoveryException::class);

        $provider->oidc()->metadata();
    }

    public function testEveryRequestNamesTheSdkAndPhpVersionsInItsUserAgent(): void
    {
        $provider = new FakeProvider();

        $provider->oidc()->metadata();

        self::assertSame(
            sprintf('appsolutely-sdk-php/%s PHP/%s', Sdk::VERSION, PHP_VERSION),
            $provider->requestsTo('GET', self::URL)[0]->getHeaderLine('User-Agent'),
        );
    }
}

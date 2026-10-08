<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Oidc\DiscoveryException;
use Appsolutely\Sdk\Sdk;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
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

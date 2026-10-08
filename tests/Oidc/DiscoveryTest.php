<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
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

    /**
     * OpenID Connect Discovery 1.0 section 4: a terminating slash is removed
     * from the issuer before the well-known path is appended, while the
     * issuer itself is still compared verbatim.
     */
    public function testAnIssuerWithATrailingSlashIsNotDoubledInTheWellKnownUrl(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['issuer'] = FakeProvider::ISSUER . '/';
        $config = $provider->config();
        $client = new Client(new Config(
            issuer: FakeProvider::ISSUER . '/',
            clientId: $config->clientId,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $config->httpClient,
            requestFactory: $config->requestFactory,
            streamFactory: $config->streamFactory,
            cache: $config->cache,
            clock: $config->clock,
        ));

        $metadata = $client->oidc()->metadata();

        self::assertSame(FakeProvider::ISSUER . '/', $metadata->issuer);
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

    public function testItKeepsTheDocumentForADayAtMostWhateverTheMaxAge(): void
    {
        $provider = new FakeProvider();
        $provider->discoveryHeaders = ['Cache-Control' => 'public, max-age=31536000'];

        $provider->oidc()->metadata();
        $provider->clock->advance(86399);
        $provider->oidc()->metadata();
        self::assertCount(1, $provider->requestsTo('GET', self::URL));

        $provider->clock->advance(1);
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

    public function testAnotherIssuerReachesTheMessageOnlyAsPrintableText(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['issuer'] = "https://evil.example.com\nFORGED";

        try {
            $provider->oidc()->metadata();
            self::fail('Another issuer was accepted.');
        } catch (DiscoveryException $exception) {
            self::assertStringContainsString('https://evil.example.com?FORGED', $exception->getMessage());
            self::assertDoesNotMatchRegularExpression('/[^\x20-\x7E]/', $exception->getMessage());
        }
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ambiguousEndpoints(): iterable
    {
        $forms = [
            'backslash before user info on the loopback' => 'http://evil.com\\@localhost/oauth/x',
            'backslash before user info' => 'https://evil.com\\@login.example.com/oauth/x',
            'user info' => 'https://a@login.example.com/oauth/x',
            'a line break' => "https://login.example.com/oauth/x\nFORGED",
            'a NUL byte' => "https://login.example.com/oauth/x\0",
        ];
        foreach (self::endpoints() as $name => [$endpoint]) {
            foreach ($forms as $form => $url) {
                yield $name . ' with ' . $form => [$endpoint, $url];
            }
        }
    }

    /**
     * The issuer's rule holds for every endpoint, including what parsers
     * read differently (see ConfigTest).
     */
    #[DataProvider('ambiguousEndpoints')]
    public function testItRefusesAnEndpointThatParsersReadDifferently(string $name, string $url): void
    {
        $provider = new FakeProvider();
        $provider->discovery[$name] = $url;

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

    /**
     * A public constructor would build metadata with plain-http or
     * ambiguous endpoints that fromArray() refuses.
     */
    public function testMetadataCanOnlyBeBuiltThroughTheValidatingFactory(): void
    {
        self::assertTrue((new \ReflectionMethod(ProviderMetadata::class, '__construct'))->isPrivate());
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

        self::assertSame(Sdk::userAgent(), $provider->requestsTo('GET', self::URL)[0]->getHeaderLine('User-Agent'));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Oidc\Pkce;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorizationUrlTest extends TestCase
{
    private const string REDIRECT = 'https://app.example.com/callback';

    public function testTheUrlCarriesTheCodeFlowParametersAndReturnsWhatTheCallerMustStore(): void
    {
        $request = (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, ['openid', 'email'], ['prompt' => 'login']);

        self::assertStringStartsWith(FakeProvider::ISSUER . '/oauth/authorize?', $request->url);
        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);
        self::assertSame([
            'response_type' => 'code',
            'client_id' => FakeProvider::CLIENT_ID,
            'redirect_uri' => self::REDIRECT,
            'scope' => 'openid email',
            'state' => $request->state,
            'nonce' => $request->nonce,
            'code_challenge' => Pkce::challengeFor($request->codeVerifier),
            'code_challenge_method' => 'S256',
            'prompt' => 'login',
        ], $query);
        self::assertSame(self::REDIRECT, $request->redirectUri);
    }

    public function testStateNonceAndVerifierAreFreshHighEntropyValues(): void
    {
        $oidc = (new FakeProvider())->oidc();

        $first = $oidc->authorizationUrl(self::REDIRECT);
        $second = $oidc->authorizationUrl(self::REDIRECT);

        foreach ([$first, $second] as $request) {
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $request->state);
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $request->nonce);
            // RFC 7636 section 4.1: 43 to 128 unreserved characters.
            self::assertMatchesRegularExpression('/^[A-Za-z0-9._~-]{43,128}$/', $request->codeVerifier);
        }
        self::assertNotSame($first->state, $second->state);
        self::assertNotSame($first->nonce, $second->nonce);
        self::assertNotSame($first->codeVerifier, $second->codeVerifier);
    }

    public function testTheChallengeIsTheS256TransformOfTheVerifier(): void
    {
        // RFC 7636 appendix B.
        self::assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            Pkce::challengeFor('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'),
        );
    }

    public function testTheOpenidScopeIsAlwaysRequested(): void
    {
        $request = (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, ['email']);

        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);
        self::assertSame('openid email', $query['scope']);
    }

    public function testExtraParametersCannotReplaceTheOnesTheFlowDependsOn(): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('state');

        (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, ['openid'], ['state' => 'chosen-by-caller']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedParameters(): iterable
    {
        foreach (['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'nonce', 'code_challenge', 'code_challenge_method', 'max_age'] as $name) {
            yield $name => [$name];
        }
        // Not supported, and each could take the flow out of the client's
        // hands: a request object (OpenID Connect Core section 6) or a
        // reference to one carries its own copies of the parameters above,
        // a response_mode changes how the code comes back, and claims asks
        // for what the ID token carries.
        foreach (['request', 'request_uri', 'response_mode', 'claims'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('reservedParameters')]
    public function testAReservedParameterCannotBePassedIn(string $name): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('"' . $name . '"');

        (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, ['openid'], [$name => 'x']);
    }

    public function testMaxAgeIsSentAndKeptWithTheRequest(): void
    {
        $request = (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, maxAge: 600);

        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);
        self::assertSame('600', $query['max_age']);
        self::assertSame(600, $request->maxAge);
    }

    public function testWithoutMaxAgeNoneIsSentOrKept(): void
    {
        $request = (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT);

        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);
        self::assertArrayNotHasKey('max_age', $query);
        self::assertNull($request->maxAge);
    }

    /**
     * Passed raw, max_age would reach the provider without the client ever
     * checking the auth_time it asks for.
     */
    public function testMaxAgeCannotBePassedAsARawParameter(): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('max_age');

        (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, ['openid'], ['max_age' => '600']);
    }

    public function testANegativeMaxAgeIsRefused(): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('max_age');

        (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, maxAge: -1);
    }

    public function testAProviderThatDoesNotOfferS256IsRefused(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['code_challenge_methods_supported'] = ['plain'];

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('S256');

        $provider->oidc()->authorizationUrl(self::REDIRECT);
    }
}

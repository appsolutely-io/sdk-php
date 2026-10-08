<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\InvalidArgumentException;
use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Oidc\Pkce;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('state');

        (new FakeProvider())->oidc()->authorizationUrl(self::REDIRECT, ['openid'], ['state' => 'chosen-by-caller']);
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

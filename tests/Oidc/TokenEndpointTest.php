<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Oidc\IdToken;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class TokenEndpointTest extends TestCase
{
    private const string TOKEN_URL = FakeProvider::ISSUER . '/oauth/token';

    public function testTheCodeExchangeAuthenticatesWithBasicByDefaultAndSendsTheVerifier(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc();
        $request = $oidc->authorizationUrl('https://app.example.com/callback');
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-1',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'rt-1',
            'scope' => 'openid',
            'id_token' => $provider->rsa->sign($provider->claims(['nonce' => $request->nonce])),
        ]);

        $tokens = $oidc->exchangeCode('the-code', $request);

        $sent = $provider->requestsTo('POST', self::TOKEN_URL)[0];
        // RFC 6749 section 2.3.1: each part form-encoded before Base64.
        self::assertSame(
            'Basic ' . base64_encode(urlencode(FakeProvider::CLIENT_ID) . ':' . urlencode(FakeProvider::CLIENT_SECRET)),
            $sent->getHeaderLine('Authorization'),
        );
        self::assertSame('application/x-www-form-urlencoded', $sent->getHeaderLine('Content-Type'));
        self::assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'the-code',
            'redirect_uri' => 'https://app.example.com/callback',
            'code_verifier' => $request->codeVerifier,
        ], FakeProvider::form($sent));
        self::assertSame('at-1', $tokens->accessToken);
        self::assertSame('rt-1', $tokens->refreshToken);
        self::assertSame($provider->clock->timestamp() + 3600, $tokens->expiresAt?->getTimestamp());
        self::assertSame('member-42', $tokens->idToken?->subject);
    }

    public function testClientSecretPostSendsTheCredentialsInTheBodyInstead(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc(ClientAuthentication::ClientSecretPost);
        $request = $oidc->authorizationUrl('https://app.example.com/callback');
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-1',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign($provider->claims(['nonce' => $request->nonce])),
        ]);

        $oidc->exchangeCode('the-code', $request);

        $sent = $provider->requestsTo('POST', self::TOKEN_URL)[0];
        self::assertFalse($sent->hasHeader('Authorization'));
        $form = FakeProvider::form($sent);
        self::assertSame(FakeProvider::CLIENT_ID, $form['client_id']);
        self::assertSame(FakeProvider::CLIENT_SECRET, $form['client_secret']);
    }

    public function testTheCodeExchangeRefusesAnIdTokenForAnotherNonce(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc();
        $request = $oidc->authorizationUrl('https://app.example.com/callback');
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-1',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign($provider->claims(['nonce' => 'replayed'])),
        ]);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('nonce');

        $oidc->exchangeCode('the-code', $request);
    }

    public function testTheCodeExchangeChecksAuthTimeAgainstTheRequestsMaxAge(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc();
        $request = $oidc->authorizationUrl('https://app.example.com/callback', maxAge: 300);
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-1',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign($provider->claims([
                'nonce' => $request->nonce,
                'auth_time' => $provider->clock->timestamp() - 3600,
            ])),
        ]);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('max_age');

        $oidc->exchangeCode('the-code', $request);
    }

    public function testTheCodeExchangeRequiresAnIdToken(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc();
        $request = $oidc->authorizationUrl('https://app.example.com/callback');
        $provider->token = fn(): ResponseInterface => $provider->json(['access_token' => 'at-1', 'token_type' => 'Bearer']);

        $this->expectException(IdTokenException::class);

        $oidc->exchangeCode('the-code', $request);
    }

    public function testExchangeCallbackValidatesTheResponseBeforeSpendingTheCode(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc();
        $request = $oidc->authorizationUrl('https://app.example.com/callback');

        try {
            $oidc->exchangeCallback(['code' => 'the-code', 'state' => 'forged', 'iss' => FakeProvider::ISSUER], $request);
            self::fail('No exception was thrown.');
        } catch (\Appsolutely\Sdk\Exception\AuthorizationResponseException) {
            self::assertSame([], $provider->requestsTo('POST', self::TOKEN_URL));
        }
    }

    public function testAnOAuthErrorBecomesATypedExceptionCarryingTheErrorCode(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc();
        $request = $oidc->authorizationUrl('https://app.example.com/callback');
        $provider->token = fn(): ResponseInterface => $provider->json([
            'error' => 'invalid_grant',
            'error_description' => 'The authorization code has expired.',
        ], 400);

        try {
            $oidc->exchangeCode('the-code', $request);
            self::fail('No exception was thrown.');
        } catch (OAuthException $exception) {
            self::assertSame('invalid_grant', $exception->error);
            self::assertSame('The authorization code has expired.', $exception->errorDescription);
            self::assertSame(400, $exception->statusCode);
        }
    }

    public function testAnErrorWithoutAnOAuthBodyIsAnUnexpectedResponse(): void
    {
        $provider = new FakeProvider();
        $oidc = $provider->oidc();
        $request = $oidc->authorizationUrl('https://app.example.com/callback');
        $provider->token = fn(): ResponseInterface => $provider->factory->createResponse(502);

        try {
            $oidc->exchangeCode('the-code', $request);
            self::fail('No exception was thrown.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame(502, $exception->statusCode);
        }
    }

    public function testATokenTypeOtherThanBearerIsRefused(): void
    {
        $provider = new FakeProvider();
        $provider->token = fn(): ResponseInterface => $provider->json(['access_token' => 'at', 'token_type' => 'mac', 'expires_in' => 60]);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('token_type');

        $provider->oidc()->machineToken();
    }

    public function testRefreshSendsTheRefreshGrantAndVerifiesARefreshedIdTokenWithoutANonce(): void
    {
        $provider = new FakeProvider();
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-2',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'rt-2',
            'id_token' => $provider->ec->sign(array_diff_key($provider->claims(), ['nonce' => true])),
        ]);

        $tokens = $provider->oidc()->refresh('rt-1', $this->originalIdToken($provider), ['openid', 'email']);

        $form = FakeProvider::form($provider->requestsTo('POST', self::TOKEN_URL)[0]);
        self::assertSame(['grant_type' => 'refresh_token', 'refresh_token' => 'rt-1', 'scope' => 'openid email'], $form);
        self::assertSame('at-2', $tokens->accessToken);
        self::assertSame('member-42', $tokens->idToken?->subject);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, string}>
     */
    public static function refreshedIdTokensForAnotherAuthentication(): iterable
    {
        yield 'another subject' => [[], ['sub' => 'member-7'], 'sub'];
        yield 'another auth_time' => [['auth_time' => 1_000], ['auth_time' => 2_000], 'auth_time'];
        yield 'an azp the original did not have' => [[], ['azp' => FakeProvider::CLIENT_ID], 'azp'];
        yield 'no azp where the original had one' => [['azp' => FakeProvider::CLIENT_ID], [], 'azp'];
    }

    /**
     * OpenID Connect Core section 12.2: a refreshed ID token keeps the
     * original's sub, azp and, when it carries one, auth_time.
     *
     * @param array<string, mixed> $original
     * @param array<string, mixed> $refreshed
     */
    #[DataProvider('refreshedIdTokensForAnotherAuthentication')]
    public function testRefreshRefusesAnIdTokenForAnotherAuthentication(array $original, array $refreshed, string $mentioned): void
    {
        $provider = new FakeProvider();
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-2',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign(array_diff_key($provider->claims($refreshed), ['nonce' => true])),
        ]);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage($mentioned);

        $provider->oidc()->refresh('rt-1', $this->originalIdToken($provider, $original));
    }

    public function testRefreshAcceptsANewIatButTheSameAuthTime(): void
    {
        $provider = new FakeProvider();
        $original = $this->originalIdToken($provider, ['auth_time' => $provider->clock->timestamp() - 10]);
        $provider->clock->advance(3600);
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-2',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign(array_diff_key($provider->claims(['auth_time' => $original->claims['auth_time']]), ['nonce' => true])),
        ]);

        $tokens = $provider->oidc()->refresh('rt-1', $original);

        self::assertSame($provider->clock->timestamp(), $tokens->idToken?->claims['iat']);
    }

    public function testTheMachineTokenUsesClientCredentialsAndIsCachedUntilShortlyBeforeItExpires(): void
    {
        $provider = new FakeProvider();
        $issued = 0;
        $provider->token = function (RequestInterface $request) use ($provider, &$issued): ResponseInterface {
            $issued++;

            return $provider->json(['access_token' => 'machine-' . $issued, 'token_type' => 'Bearer', 'expires_in' => 600]);
        };

        $first = $provider->oidc()->machineToken(['entitlements:read']);
        $provider->clock->advance(500);
        $second = $provider->oidc()->machineToken(['entitlements:read']);
        $provider->clock->advance(60);
        $third = $provider->oidc()->machineToken(['entitlements:read']);

        self::assertSame('machine-1', $first->accessToken);
        self::assertSame('machine-1', $second->accessToken);
        self::assertSame('machine-2', $third->accessToken);
        self::assertSame(
            ['grant_type' => 'client_credentials', 'scope' => 'entitlements:read'],
            FakeProvider::form($provider->requestsTo('POST', self::TOKEN_URL)[0]),
        );
    }

    /**
     * The cached copy is rebuilt from a stored timestamp; it must come back
     * in the injected clock's zone like the fresh one, not the PHP default.
     */
    public function testACachedMachineTokenExpiresInTheClocksTimeZone(): void
    {
        $provider = new FakeProvider(new FrozenClock('2026-10-08T14:00:00+02:00'));
        $provider->token = fn(): ResponseInterface => $provider->json(['access_token' => 'machine', 'token_type' => 'Bearer', 'expires_in' => 600]);
        $defaultZone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            $fresh = $provider->oidc()->machineToken();
            $cached = $provider->oidc()->machineToken();
        } finally {
            date_default_timezone_set($defaultZone);
        }

        self::assertCount(1, $provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame('2026-10-08T14:10:00+02:00', $fresh->expiresAt?->format(DATE_ATOM));
        self::assertSame('2026-10-08T14:10:00+02:00', $cached->expiresAt?->format(DATE_ATOM));
    }

    public function testMachineTokensForDifferentScopesAreCachedApart(): void
    {
        $provider = new FakeProvider();
        $issued = 0;
        $provider->token = function () use ($provider, &$issued): ResponseInterface {
            $issued++;

            return $provider->json(['access_token' => 'machine-' . $issued, 'token_type' => 'Bearer', 'expires_in' => 600]);
        };

        $a = $provider->oidc()->machineToken(['a']);
        $b = $provider->oidc()->machineToken(['b']);

        self::assertNotSame($a->accessToken, $b->accessToken);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function originalIdToken(FakeProvider $provider, array $claims = []): IdToken
    {
        return $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims($claims)), 'the-nonce');
    }
}

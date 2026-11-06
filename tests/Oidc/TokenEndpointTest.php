<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Oidc\IdToken;
use Appsolutely\Sdk\Oidc\OpenIdClient;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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

        $original = $this->originalIdToken($provider);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('token_type');

        $provider->oidc()->refresh('rt-1', $original);
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

    /**
     * A refresh answer may leave the ID token out (OpenID Connect Core
     * section 12.2); the original is carried forward, so the token set to
     * keep for the next refresh always holds the one to compare against.
     */
    public function testARefreshWithoutAnIdTokenCarriesTheOriginalForward(): void
    {
        $provider = new FakeProvider();
        $original = $this->originalIdToken($provider);
        $provider->token = fn(): ResponseInterface => $provider->json(['access_token' => 'at-2', 'token_type' => 'Bearer', 'refresh_token' => 'rt-2']);

        $tokens = $provider->oidc()->refresh('rt-1', $original);

        self::assertSame($original, $tokens->idToken);
        self::assertSame('at-2', $tokens->accessToken);
        self::assertSame('rt-2', $tokens->refreshToken);
    }

    /**
     * A refreshed ID token without auth_time cannot be compared on it; it
     * keeps the original authentication's time, so the refresh after it is
     * still held to that time rather than to none.
     */
    public function testARefreshedIdTokenWithoutAuthTimeKeepsTheOriginalOneForTheNextRefresh(): void
    {
        $provider = new FakeProvider();
        $authTime = $provider->clock->timestamp() - 10;
        $original = $this->originalIdToken($provider, ['auth_time' => $authTime]);
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-2',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign(array_diff_key($provider->claims(), ['nonce' => true])),
        ]);

        $refreshed = $provider->oidc()->refresh('rt-1', $original)->idToken;

        self::assertNotNull($refreshed);
        self::assertArrayNotHasKey('auth_time', $refreshed->claims);
        self::assertSame($authTime, $refreshed->authTime);

        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-3',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign(array_diff_key($provider->claims(['auth_time' => $authTime + 1000]), ['nonce' => true])),
        ]);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('auth_time');

        $provider->oidc()->refresh('rt-2', $refreshed);
    }

    public function testARefreshedIdTokenMayAddAnAuthTimeTheOriginalLacked(): void
    {
        $provider = new FakeProvider();
        $original = $this->originalIdToken($provider);
        $authTime = $provider->clock->timestamp() - 10;
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-2',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign(array_diff_key($provider->claims(['auth_time' => $authTime]), ['nonce' => true])),
        ]);

        self::assertSame($authTime, $provider->oidc()->refresh('rt-1', $original)->idToken?->authTime);
    }

    /**
     * Without the original, a refreshed ID token for another member would
     * pass unchecked.
     */
    public function testRefreshRequiresTheOriginalIdToken(): void
    {
        $parameter = new \ReflectionParameter([OpenIdClient::class, 'refresh'], 'originalIdToken');

        self::assertFalse($parameter->allowsNull());
        self::assertFalse($parameter->isOptional());
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function originalIdToken(FakeProvider $provider, array $claims = []): IdToken
    {
        return $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims($claims)), 'the-nonce');
    }
}

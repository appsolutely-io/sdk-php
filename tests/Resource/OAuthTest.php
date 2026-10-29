<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Request\OAuth\AuthorizationCodeGrant;
use Appsolutely\Sdk\Request\OAuth\ClientCredentialsGrant;
use Appsolutely\Sdk\Request\OAuth\ClientIdentity;
use Appsolutely\Sdk\Request\OAuth\DeviceAuthorizationRequest;
use Appsolutely\Sdk\Request\OAuth\DeviceCodeGrant;
use Appsolutely\Sdk\Request\OAuth\RefreshTokenGrant;
use Appsolutely\Sdk\Request\OAuth\RevocationRequest;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OAuthTest extends TestCase
{
    public function testEachGrantSendsOnlyItsOwnFormFields(): void
    {
        $grants = [
            [new AuthorizationCodeGrant('code', 'https://app.example.test/return', 'verifier', 'openid'), ['grant_type' => 'authorization_code', 'code' => 'code', 'redirect_uri' => 'https://app.example.test/return', 'code_verifier' => 'verifier', 'scope' => 'openid']],
            [new RefreshTokenGrant('refresh', 'openid email'), ['grant_type' => 'refresh_token', 'refresh_token' => 'refresh', 'scope' => 'openid email']],
            [new ClientCredentialsGrant('roles:read'), ['grant_type' => 'client_credentials', 'scope' => 'roles:read']],
            [new DeviceCodeGrant('device', 'profile'), ['grant_type' => 'urn:ietf:params:oauth:grant-type:device_code', 'device_code' => 'device', 'scope' => 'profile']],
        ];
        foreach ($grants as [$grant, $body]) {
            $fake = (new FakeClient())->answer(Operation::ExchangeOAuthToken, ['access_token' => 'access', 'token_type' => 'Bearer', 'expires_in' => 137, 'id_token' => 'raw-id']);
            $tokens = $fake->api()->oauth()->exchange($grant, new ClientIdentity('public'));
            self::assertSame([...$body, 'client_id' => 'public'], $fake->lastCall(Operation::ExchangeOAuthToken)->body);
            self::assertSame('access', $tokens->accessToken);
            self::assertSame('raw-id', $tokens->idToken);
            self::assertSame(137, $tokens->expiresIn);
            self::assertCount(1, $fake->calls());
        }
    }

    public function testDeviceAuthorizationAndRevocationAreOneRequestEach(): void
    {
        $fake = (new FakeClient())
            ->answer(Operation::RequestDeviceAuthorization, ['device_code' => 'device', 'user_code' => 'user', 'verification_uri' => 'https://site.test/activate', 'expires_in' => 213, 'interval' => 7])
            ->answer(Operation::RevokeOAuthToken);
        $device = $fake->api()->oauth()->deviceAuthorization(new DeviceAuthorizationRequest('openid'), new ClientIdentity('client', 'secret', ClientAuthentication::ClientSecretPost));
        self::assertSame(7, $device->interval);
        self::assertSame(['scope' => 'openid', 'client_id' => 'client', 'client_secret' => 'secret'], $fake->lastCall(Operation::RequestDeviceAuthorization)->body);
        self::assertSame(200, $fake->api()->oauth()->revoke(new RevocationRequest('access', 'access_token'), new ClientIdentity('client'))->status);
        self::assertCount(2, $fake->calls());
    }

    public function testPendingDeviceAnswersAreThrownWithoutPolling(): void
    {
        $site = (new SiteRecorder())->json(['error' => 'slow_down', 'error_description' => 'Wait'], 400);
        try {
            $site->provider->client()->api()->oauth()->exchange(new DeviceCodeGrant('device'), new ClientIdentity('public'));
            self::fail('Expected OAuth refusal.');
        } catch (OAuthException $error) {
            self::assertSame('slow_down', $error->error);
            self::assertSame(400, $error->statusCode);
        }
        self::assertCount(1, $site->provider->siteRequests());
    }

    public function testOAuthRefusalsKeepHintAndUnknownExtensions(): void
    {
        $site = (new SiteRecorder())->json(['error' => 'invalid_grant', 'error_description' => 'Grant refused', 'error_uri' => 'https://site.example.test/errors/grant', 'hint' => 'use the current refresh token', 'future' => ['retryable' => false]], 400);
        try {
            $site->provider->client()->api()->oauth()->exchange(new RefreshTokenGrant('old-refresh'), new ClientIdentity('client'));
            self::fail('The refusal was not thrown.');
        } catch (OAuthException $error) {
            self::assertSame(['hint' => 'use the current refresh token', 'future' => ['retryable' => false]], $error->extra);
            self::assertSame('invalid_grant', $error->error);
            self::assertSame('Grant refused', $error->errorDescription);
            self::assertSame('https://site.example.test/errors/grant', $error->errorUri);
            self::assertSame(400, $error->statusCode);
        }
    }

    public function testBasicAuthenticationIsEncodedWithoutBodyCredentials(): void
    {
        $site = (new SiteRecorder())->json(['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 40]);
        $site->provider->client()->api()->oauth()->exchange(new ClientCredentialsGrant(), new ClientIdentity('id:/', 'secret:+'));
        self::assertSame('Basic ' . base64_encode(urlencode('id:/') . ':' . urlencode('secret:+')), $site->request()->getHeaderLine('Authorization'));
        self::assertSame('application/x-www-form-urlencoded', $site->request()->getHeaderLine('Content-Type'));
        self::assertSame('grant_type=client_credentials', (string) $site->request()->getBody());
    }

    public function testUserInfoHasItsOwnInlineEntitlementShape(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetOAuthUserInfo, ['sub' => 'subject', 'roles' => ['editor'], 'entitlements' => [['key' => 'pro', 'label' => 'Pro', 'quantity' => 1, 'expires_at' => null, 'period_start' => null, 'period_end' => null, 'breakdown' => null]], 'entitlement_values' => ['pro' => ['kind' => 'boolean', 'value' => true, 'future' => 'kept']]]);
        $info = $fake->forMember()->api()->userInfo();
        self::assertSame('subject', $info->sub);
        self::assertSame('pro', $info->entitlements[0]->key ?? null);
        self::assertTrue($info->entitlementValues['pro']->value ?? false);
        self::assertSame('kept', $info->entitlementValues['pro']->extra['future'] ?? null);
        self::assertSame(FakeClient::MEMBER_TOKEN, $fake->lastCall(Operation::GetOAuthUserInfo)->token);
    }

    public function testUserInfoOAuthErrorsAreExposedWithoutRetrying(): void
    {
        foreach ([401 => 'invalid_token', 503 => 'temporarily_unavailable'] as $status => $code) {
            $site = (new SiteRecorder())->json(['error' => $code], $status);
            try {
                $site->provider->client()->forMember('member-token')->api()->userInfo();
                self::fail('The OAuth refusal was lost.');
            } catch (OAuthException $error) {
                self::assertSame($code, $error->error);
                self::assertSame($status, $error->statusCode);
            }
            self::assertCount(1, $site->provider->siteRequests());
            self::assertSame([], $site->provider->sleeper->sleeps);
        }
    }

    public function testBothSigningKeyShapesAreReadWithoutVerifyingAToken(): void
    {
        $keys = [
            ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'rsa', 'n' => 'n', 'e' => 'e'],
            ['kty' => 'EC', 'use' => 'sig', 'alg' => 'ES256', 'kid' => 'ec', 'crv' => 'P-256', 'x' => 'x', 'y' => 'y'],
        ];
        $fake = (new FakeClient())->answer(Operation::GetOpenIdSigningKeys, ['keys' => $keys]);
        $set = $fake->api()->signingKeys();
        self::assertSame('RSA', $set->keys[0]->kty);
        self::assertSame('x', $set->keys[1]->x);
    }

    public function testGrantRequiredValuesCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentValueException::class);
        new RefreshTokenGrant('');
    }
}

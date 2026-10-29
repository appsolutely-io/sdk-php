<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Request\OAuth;

use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Model\OAuthDeviceAuthorization;
use Appsolutely\Sdk\Model\OAuthToken;
use Appsolutely\Sdk\Request\OAuth\AuthorizationCodeGrant;
use Appsolutely\Sdk\Request\OAuth\ClientCredentialsGrant;
use Appsolutely\Sdk\Request\OAuth\DeviceCodeGrant;
use Appsolutely\Sdk\Request\OAuth\RefreshTokenGrant;
use Appsolutely\Sdk\Request\OAuth\RevocationRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @phpstan-type SecretHolder OAuthToken|OAuthDeviceAuthorization|AuthorizationCodeGrant|RefreshTokenGrant|ClientCredentialsGrant|DeviceCodeGrant|RevocationRequest */
final class SecretExposureTest extends TestCase
{
    /** @return iterable<string, array{object, list<string>, list<string>}> */
    public static function holders(): iterable
    {
        yield 'token' => [new OAuthToken('access-sentinel', 'Bearer', 137, 'refresh-sentinel', 'id-sentinel', ['future_token' => 'extra-sentinel']), ['access-sentinel', 'refresh-sentinel', 'id-sentinel', 'extra-sentinel'], ['accessToken', 'tokenType', 'expiresIn', 'refreshToken', 'idToken']];
        yield 'device authorization' => [new OAuthDeviceAuthorization('device-sentinel', 'user-sentinel', 'https://site.example.test/activate', 'https://site.example.test/activate?user_code=user-sentinel', 213, 7, ['future_token' => 'extra-sentinel']), ['device-sentinel', 'user-sentinel', 'extra-sentinel'], ['deviceCode', 'userCode', 'verificationUri', 'verificationUriComplete', 'expiresIn', 'interval']];
        yield 'authorization code' => [new AuthorizationCodeGrant('code-sentinel', 'https://app.example.test/return', 'verifier-sentinel'), ['code-sentinel', 'verifier-sentinel'], ['code', 'redirectUri', 'codeVerifier', 'scope']];
        yield 'refresh' => [new RefreshTokenGrant('refresh-sentinel'), ['refresh-sentinel'], ['refreshToken', 'scope']];
        yield 'client credentials' => [new ClientCredentialsGrant('openid'), [], ['scope']];
        yield 'device code' => [new DeviceCodeGrant('device-sentinel'), ['device-sentinel'], ['deviceCode', 'scope']];
        yield 'revocation' => [new RevocationRequest('token-sentinel'), ['token-sentinel'], ['token', 'tokenTypeHint']];
    }

    /**
     * @param SecretHolder $holder
     * @param list<string> $secrets
     * @param list<string> $fields
     */
    #[DataProvider('holders')]
    public function testDebugOutputUsesAnExplicitRedactedFieldList(object $holder, array $secrets, array $fields): void
    {
        ob_start();
        var_dump($holder);
        $dumps = [(string) ob_get_clean(), print_r($holder, true)];
        foreach ($dumps as $dump) {
            foreach ($secrets as $secret) {
                self::assertStringNotContainsString($secret, $dump);
            }
        }
        self::assertSame($fields, array_keys($holder->__debugInfo()));
    }

    /**
     * @param SecretHolder $holder
     * @param list<string> $secrets
     * @param list<string> $fields
     */
    #[DataProvider('holders')]
    public function testSerializationRefusesTheCredentialHolder(object $holder, array $secrets, array $fields): void
    {
        $this->expectException(NotSerializableException::class);
        serialize($holder);
    }

    /**
     * @param SecretHolder $holder
     * @param list<string> $secrets
     * @param list<string> $fields
     */
    #[DataProvider('holders')]
    public function testRestorationRefusesTheCredentialHolder(object $holder, array $secrets, array $fields): void
    {
        $this->expectException(NotSerializableException::class);
        $class = $holder::class;
        unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class UserInfoAndRevocationTest extends TestCase
{
    public function testUserInfoIsReadWithTheAccessTokenAsABearer(): void
    {
        $provider = new FakeProvider();
        $provider->userInfo = fn(): ResponseInterface => $provider->json(['sub' => 'member-42', 'email' => 'm@example.com']);

        $claims = $provider->oidc()->userInfo('at-1', 'member-42');

        self::assertSame(['sub' => 'member-42', 'email' => 'm@example.com'], $claims);
        $sent = $provider->requestsTo('GET', FakeProvider::ISSUER . '/oauth/userinfo')[0];
        self::assertSame('Bearer at-1', $sent->getHeaderLine('Authorization'));
    }

    public function testUserInfoForAnotherSubjectThanTheIdTokensIsRefused(): void
    {
        $provider = new FakeProvider();
        $provider->userInfo = fn(): ResponseInterface => $provider->json(['sub' => 'member-7']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('sub');

        $provider->oidc()->userInfo('at-1', 'member-42');
    }

    public function testUserInfoRefusedForAnInvalidTokenIsAnOAuthException(): void
    {
        $provider = new FakeProvider();
        $provider->userInfo = fn(): ResponseInterface => $provider->json(['error' => 'invalid_token'], 401);

        try {
            $provider->oidc()->userInfo('expired');
            self::fail('No exception was thrown.');
        } catch (OAuthException $exception) {
            self::assertSame('invalid_token', $exception->error);
        }
    }

    public function testRevocationPostsTheTokenAndAuthenticatesTheClientWithBasic(): void
    {
        $provider = new FakeProvider();
        $provider->revoke = fn(): ResponseInterface => $provider->factory->createResponse(200);

        $provider->oidc()->revoke('rt-1', 'refresh_token');

        $sent = $provider->requestsTo('POST', FakeProvider::ISSUER . '/oauth/revoke')[0];
        self::assertStringStartsWith('Basic ', $sent->getHeaderLine('Authorization'));
        self::assertSame(['token' => 'rt-1', 'token_type_hint' => 'refresh_token'], FakeProvider::form($sent));
    }

    public function testARevocationErrorBecomesAnOAuthException(): void
    {
        $provider = new FakeProvider();
        $provider->revoke = fn(): ResponseInterface => $provider->json(['error' => 'unsupported_token_type'], 400);

        try {
            $provider->oidc()->revoke('rt-1', 'refresh_token');
            self::fail('No exception was thrown.');
        } catch (OAuthException $exception) {
            self::assertSame('unsupported_token_type', $exception->error);
        }
    }
}

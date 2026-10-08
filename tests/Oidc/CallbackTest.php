<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Oidc\AuthorizationRequest;
use Appsolutely\Sdk\Exception\AuthorizationResponseException;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\TestCase;

final class CallbackTest extends TestCase
{
    private FakeProvider $provider;
    private AuthorizationRequest $request;

    protected function setUp(): void
    {
        $this->provider = new FakeProvider();
        $this->request = $this->provider->oidc()->authorizationUrl('https://app.example.com/callback');
    }

    public function testItReturnsTheCodeWhenStateAndIssuerMatch(): void
    {
        $code = $this->provider->oidc()->validateCallback(
            ['code' => 'the-code', 'state' => $this->request->state, 'iss' => FakeProvider::ISSUER],
            $this->request,
        );

        self::assertSame('the-code', $code);
    }

    public function testItRefusesAStateOtherThanTheOneIssued(): void
    {
        $this->expectException(AuthorizationResponseException::class);
        $this->expectExceptionMessage('state');

        $this->provider->oidc()->validateCallback(
            ['code' => 'the-code', 'state' => 'forged', 'iss' => FakeProvider::ISSUER],
            $this->request,
        );
    }

    public function testItRefusesAnIssParameterNamingAnotherIssuer(): void
    {
        $this->expectException(AuthorizationResponseException::class);
        $this->expectExceptionMessage('iss');

        $this->provider->oidc()->validateCallback(
            ['code' => 'the-code', 'state' => $this->request->state, 'iss' => 'https://evil.example.com'],
            $this->request,
        );
    }

    public function testItRefusesAMissingIssWhenTheProviderAdvertisesIt(): void
    {
        $this->expectException(AuthorizationResponseException::class);
        $this->expectExceptionMessage('iss');

        $this->provider->oidc()->validateCallback(
            ['code' => 'the-code', 'state' => $this->request->state],
            $this->request,
        );
    }

    public function testItAcceptsAMissingIssWhenTheProviderDoesNotAdvertiseIt(): void
    {
        $this->provider->discovery['authorization_response_iss_parameter_supported'] = false;
        $this->provider->cache->clear();

        $code = $this->provider->oidc()->validateCallback(
            ['code' => 'the-code', 'state' => $this->request->state],
            $this->request,
        );

        self::assertSame('the-code', $code);
    }

    public function testAnErrorResponseBecomesAnOAuthExceptionCarryingItsCode(): void
    {
        try {
            $this->provider->oidc()->validateCallback(
                ['error' => 'access_denied', 'error_description' => 'The member declined.', 'state' => $this->request->state, 'iss' => FakeProvider::ISSUER],
                $this->request,
            );
            self::fail('No exception was thrown.');
        } catch (OAuthException $exception) {
            self::assertSame('access_denied', $exception->error);
            self::assertSame('The member declined.', $exception->errorDescription);
        }
    }

    public function testTheErrorReachesTheMessageOnlyAsPrintableText(): void
    {
        try {
            $this->provider->oidc()->validateCallback(
                ['error' => "access_denied\nFORGED", 'error_description' => "Declined.\r\nFORGED " . str_repeat('x', 300), 'state' => $this->request->state, 'iss' => FakeProvider::ISSUER],
                $this->request,
            );
            self::fail('No exception was thrown.');
        } catch (OAuthException $exception) {
            self::assertStringContainsString('access_denied?FORGED', $exception->getMessage());
            self::assertStringContainsString('Declined.??FORGED', $exception->getMessage());
            self::assertDoesNotMatchRegularExpression('/[^\x20-\x7E]/', $exception->getMessage());
            self::assertLessThan(300, strlen($exception->getMessage()));
            self::assertSame("access_denied\nFORGED", $exception->error);
        }
    }

    public function testItRefusesAResponseWithoutACode(): void
    {
        $this->expectException(AuthorizationResponseException::class);
        $this->expectExceptionMessage('code');

        $this->provider->oidc()->validateCallback(
            ['state' => $this->request->state, 'iss' => FakeProvider::ISSUER],
            $this->request,
        );
    }
}

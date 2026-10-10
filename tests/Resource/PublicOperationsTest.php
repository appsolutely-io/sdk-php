<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Exception\ValidationFailedException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The version record, the API document and the magic-link sign-in are
 * open to anyone, so the client sends them without a credential even when
 * it holds the administrator token.
 */
final class PublicOperationsTest extends TestCase
{
    public function testTheVersionIsReadWithoutACredential(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json([
            'version' => 'v1',
            'status' => 'deprecated',
            'deprecation' => '2026-10-01T00:00:00Z',
            'sunset' => '2027-04-01T09:30:00+02:00',
            'successor' => 'v2',
            'documentation' => null,
            'added_later' => true,
        ]));

        $version = $provider->client()->api()->version();

        $request = $provider->siteRequests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(FakeProvider::BASE_URL . '/api/v1', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame('v1', $version->version);
        self::assertSame('deprecated', $version->status);
        self::assertSame('2026-10-01T00:00:00+00:00', $version->deprecation?->format(DATE_ATOM));
        self::assertSame('2027-04-01T07:30:00+00:00', $version->sunset?->format(DATE_ATOM));
        self::assertSame('v2', $version->successor);
        self::assertNull($version->documentation);
        self::assertTrue($version->extra['added_later']);
    }

    public function testTheApiDocumentIsReadWithoutACredential(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json(['openapi' => '3.1.0', 'paths' => []]));

        $document = $provider->client()->api()->openApiDocument();

        self::assertSame(FakeProvider::BASE_URL . '/api/v1/openapi.json', (string) $provider->siteRequests()[0]->getUri());
        self::assertFalse($provider->siteRequests()[0]->hasHeader('Authorization'));
        self::assertSame('3.1.0', $document['openapi']);
    }

    public function testAMagicLinkIsRequestedWithoutACredential(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->factory->createResponse(202));

        $provider->client()->api()->magicLink()->request('ada@example.com', ['me:read', 'me:write'], 'Ada\'s phone', 'challenge-123');

        $request = $provider->siteRequests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(FakeProvider::BASE_URL . '/api/v1/auth/magic-link', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame(
            ['email' => 'ada@example.com', 'abilities' => ['me:read', 'me:write'], 'device_name' => 'Ada\'s phone', 'code_challenge' => 'challenge-123'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testAMagicLinkIsExchangedForAMemberToken(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json(['token' => '12|member-token', 'abilities' => ['me:read'], 'expires_at' => null], 201));

        $token = $provider->client()->api()->magicLink()->exchange('https://site.example.com/magic/abc', 'verifier-xyz', code: '123456');

        $request = $provider->siteRequests()[0];
        self::assertSame(FakeProvider::BASE_URL . '/api/v1/auth/magic-link/token', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame(['link' => 'https://site.example.com/magic/abc', 'code_verifier' => 'verifier-xyz', 'code' => '123456'], json_decode((string) $request->getBody(), true));
        self::assertSame('12|member-token', $token->token);
        self::assertSame(['me:read'], $token->abilities);
        self::assertNull($token->expiresAt);
    }

    public function testDumpingAMemberTokenHidesIt(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json(['token' => '12|member-token', 'abilities' => []], 201));

        $token = $provider->client()->api()->magicLink()->exchange('https://site.example.com/magic/abc', 'verifier-xyz');

        self::assertStringNotContainsString('member-token', print_r($token, true));
    }

    public function testARefusedExchangeIsItsProblem(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->problem(422, 'validation-failed', ['errors' => ['link' => ['The link has expired.']]]));

        try {
            $provider->client()->api()->magicLink()->exchange('https://site.example.com/magic/old', 'verifier');
            self::fail('The refusal was not thrown.');
        } catch (ValidationFailedException $exception) {
            self::assertSame(['link' => ['The link has expired.']], $exception->errors);
        }
    }

    public function testAnAnswerMissingARequiredFieldIsRefused(): void
    {
        $provider = self::answering(static fn(FakeProvider $provider): ResponseInterface => $provider->json(['status' => 'current']));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"version"');

        $provider->client()->api()->version();
    }

    /**
     * @param \Closure(FakeProvider): ResponseInterface $answer
     */
    private static function answering(\Closure $answer): FakeProvider
    {
        $provider = new FakeProvider();
        $provider->site = static fn(RequestInterface $request): ResponseInterface => $answer($provider);

        return $provider;
    }
}

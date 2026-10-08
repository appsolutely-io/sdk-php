<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Oidc\IdToken;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * An integrator who keeps the session as JSON stores the ID token with
 * toArray() and gives it back to the next refresh with fromTrustedStorage().
 */
final class IdTokenStorageTest extends TestCase
{
    public function testAnIdTokenComesBackFromJsonAsItWasStored(): void
    {
        $provider = new FakeProvider();
        $original = $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims(['auth_time' => 1_700_000_000, 'email' => 'm@example.com'])), 'the-nonce');

        $restored = IdToken::fromTrustedStorage(self::throughJson($original->toArray()));

        self::assertSame($original->raw, $restored->raw);
        self::assertSame($original->subject, $restored->subject);
        self::assertSame($original->claims, $restored->claims);
        self::assertSame($original->authTime, $restored->authTime);
    }

    /**
     * The carried-forward auth_time of a refreshed token that had none is
     * stored too, so a refresh after a restore is still held to it.
     */
    public function testARestoredIdTokenStillHoldsTheNextRefreshToTheOriginalAuthentication(): void
    {
        $provider = new FakeProvider();
        $authTime = $provider->clock->timestamp() - 10;
        $original = $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims(['auth_time' => $authTime])), 'the-nonce');
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-2',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign(array_diff_key($provider->claims(), ['nonce' => true])),
        ]);
        $refreshed = $provider->oidc()->refresh('rt-1', $original)->idToken;
        self::assertNotNull($refreshed);

        $restored = IdToken::fromTrustedStorage(self::throughJson($refreshed->toArray()));
        $provider->token = fn(): ResponseInterface => $provider->json([
            'access_token' => 'at-3',
            'token_type' => 'Bearer',
            'id_token' => $provider->rsa->sign(array_diff_key($provider->claims(['auth_time' => $authTime + 1000]), ['nonce' => true])),
        ]);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('auth_time');

        $provider->oidc()->refresh('rt-2', $restored);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function notWhatToArrayReturns(): iterable
    {
        $claims = ['iss' => FakeProvider::ISSUER, 'sub' => 'member-42', 'auth_time' => 1_700_000_000];
        $stored = ['raw' => 'a.b.c', 'claims' => $claims, 'auth_time' => 1_700_000_000];

        yield 'nothing' => [[]];
        yield 'no raw token' => [array_diff_key($stored, ['raw' => true])];
        yield 'an empty raw token' => [['raw' => ''] + $stored];
        yield 'no claims' => [array_diff_key($stored, ['claims' => true])];
        yield 'claims that are a list' => [['claims' => ['member-42']] + $stored];
        yield 'claims without sub' => [['claims' => array_diff_key($claims, ['sub' => true])] + $stored];
        yield 'claims with an empty sub' => [['claims' => ['sub' => ''] + $claims] + $stored];
        yield 'no auth_time entry' => [array_diff_key($stored, ['auth_time' => true])];
        yield 'an auth_time that is not a number' => [['auth_time' => '1700000000'] + $stored];
        yield 'an auth_time other than the claim' => [['auth_time' => 1_700_000_001] + $stored];
    }

    /**
     * @param array<mixed> $stored
     */
    #[DataProvider('notWhatToArrayReturns')]
    public function testRestoringRefusesWhatToArrayNeverReturns(array $stored): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        IdToken::fromTrustedStorage($stored);
    }

    /**
     * @param array<string, mixed> $value
     * @return array<mixed>
     */
    private static function throughJson(array $value): array
    {
        $decoded = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}

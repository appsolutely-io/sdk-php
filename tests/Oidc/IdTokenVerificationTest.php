<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\SigningKey;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdTokenVerificationTest extends TestCase
{
    private const string JWKS_URL = FakeProvider::ISSUER . '/oauth/jwks.json';

    public function testAnRs256TokenSignedByAPublishedKeyIsAccepted(): void
    {
        $provider = new FakeProvider();

        $token = $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims()), 'the-nonce');

        self::assertSame('member-42', $token->subject);
        self::assertSame('member-42', $token->claims['sub']);
    }

    public function testAnEs256TokenSignedByAPublishedKeyIsAccepted(): void
    {
        $provider = new FakeProvider();

        $token = $provider->oidc()->verifyIdToken($provider->ec->sign($provider->claims()), 'the-nonce');

        self::assertSame('member-42', $token->subject);
    }

    public function testATokenSignedByAKeyThatIsNotPublishedIsRefused(): void
    {
        $provider = new FakeProvider();
        $impostor = SigningKey::rsa('rsa-1');

        $this->expectException(IdTokenException::class);

        $provider->oidc()->verifyIdToken($impostor->sign($provider->claims()), 'the-nonce');
    }

    public function testAnAlgorithmTheProviderDoesNotAdvertiseIsRefused(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['id_token_signing_alg_values_supported'] = ['RS256'];

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('ES256');

        $provider->oidc()->verifyIdToken($provider->ec->sign($provider->claims()), 'the-nonce');
    }

    public function testASymmetricAlgorithmIsRefusedEvenWhenAdvertised(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['id_token_signing_alg_values_supported'] = ['RS256', 'ES256', 'HS256'];
        $token = JWT::encode($provider->claims(), FakeProvider::CLIENT_SECRET . str_repeat('x', 32), 'HS256', 'rsa-1');

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('HS256');

        $provider->oidc()->verifyIdToken($token, 'the-nonce');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badClaims(): iterable
    {
        yield 'another issuer' => [['iss' => 'https://evil.example.com'], 'iss'];
        yield 'issuer with a trailing slash' => [['iss' => FakeProvider::ISSUER . '/'], 'iss'];
        yield 'audience without this client' => [['aud' => 'someone-else'], 'aud does not contain this client'];
        yield 'another audience beside this client' => [['aud' => [FakeProvider::CLIENT_ID, 'other']], 'other than this client'];
        // Section 3.1.3.7 rule 3: an audience the client does not trust is a
        // refusal even when azp names this client.
        yield 'another audience beside this client and azp' => [['aud' => [FakeProvider::CLIENT_ID, 'other'], 'azp' => FakeProvider::CLIENT_ID], 'other than this client'];
        yield 'an audience that is not a string' => [['aud' => [FakeProvider::CLIENT_ID, 7]], 'other than this client'];
        yield 'an empty audience list' => [['aud' => []], 'aud does not contain this client'];
        yield 'one audience repeated without azp' => [['aud' => [FakeProvider::CLIENT_ID, FakeProvider::CLIENT_ID]], 'has no azp'];
        yield 'azp naming another client' => [['azp' => 'someone-else'], 'azp'];
        yield 'missing subject' => [['sub' => ''], 'sub'];
        yield 'missing exp' => [['exp' => null], 'exp'];
        yield 'missing iat' => [['iat' => null], 'iat'];
        yield 'missing nonce' => [['nonce' => null], 'nonce'];
        yield 'another nonce' => [['nonce' => 'replayed'], 'nonce'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('badClaims')]
    public function testAClaimThatFailsItsRuleIsRefused(array $overrides, string $mentioned): void
    {
        $provider = new FakeProvider();
        $claims = array_filter($provider->claims($overrides), static fn(mixed $value): bool => $value !== null);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage($mentioned);

        $provider->oidc()->verifyIdToken($provider->rsa->sign($claims), 'the-nonce');
    }

    public function testAnAudienceListOfThisClientAloneIsAccepted(): void
    {
        $provider = new FakeProvider();
        $claims = $provider->claims(['aud' => [FakeProvider::CLIENT_ID]]);

        $token = $provider->oidc()->verifyIdToken($provider->rsa->sign($claims), 'the-nonce');

        self::assertSame('member-42', $token->subject);
    }

    public function testAnExpiredTokenIsRefusedOnceTheLeewayHasPassed(): void
    {
        $provider = new FakeProvider();
        $jwt = $provider->rsa->sign($provider->claims(['exp' => $provider->clock->timestamp() - 30]));

        self::assertSame('member-42', $provider->oidc(leeway: 60)->verifyIdToken($jwt, 'the-nonce')->subject);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('exp');

        $provider->oidc(leeway: 10)->verifyIdToken($jwt, 'the-nonce');
    }

    public function testATokenIssuedInTheFutureIsRefusedBeyondTheLeeway(): void
    {
        $provider = new FakeProvider();
        $jwt = $provider->rsa->sign($provider->claims(['iat' => $provider->clock->timestamp() + 120]));

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('iat');

        $provider->oidc(leeway: 60)->verifyIdToken($jwt, 'the-nonce');
    }

    public function testAnAccessTokenHashThatDoesNotMatchIsRefused(): void
    {
        $provider = new FakeProvider();
        $hash = JWT::urlsafeB64Encode(substr(hash('sha256', 'the-access-token', true), 0, 16));
        $jwt = $provider->rsa->sign($provider->claims(['at_hash' => $hash]));

        self::assertSame('member-42', $provider->oidc()->verifyIdToken($jwt, 'the-nonce', 'the-access-token')->subject);

        $this->expectException(IdTokenException::class);
        $this->expectExceptionMessage('at_hash');

        $provider->oidc()->verifyIdToken($jwt, 'the-nonce', 'another-access-token');
    }

    public function testTheKeySetIsCachedBetweenVerifications(): void
    {
        $provider = new FakeProvider();

        $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims()), 'the-nonce');
        $provider->oidc()->verifyIdToken($provider->ec->sign($provider->claims()), 'the-nonce');

        self::assertCount(1, $provider->requestsTo('GET', self::JWKS_URL));
    }

    public function testAnUnknownKidRefetchesTheKeySetOnceAndFindsARotatedKey(): void
    {
        $provider = new FakeProvider();
        $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims()), 'the-nonce');
        $provider->clock->advance(61);
        $rotated = SigningKey::rsa('rsa-2');
        $provider->jwks[] = $rotated->jwk;

        $token = $provider->oidc()->verifyIdToken($rotated->sign($provider->claims()), 'the-nonce');

        self::assertSame('member-42', $token->subject);
        self::assertCount(2, $provider->requestsTo('GET', self::JWKS_URL));
    }

    public function testAnUnknownKidRefetchesAtMostOncePerMinute(): void
    {
        $provider = new FakeProvider();
        $unknown = SigningKey::rsa('nobody-published-this');
        $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims()), 'the-nonce');
        $provider->clock->advance(61);

        foreach ([1, 2, 3] as $attempt) {
            try {
                $provider->oidc()->verifyIdToken($unknown->sign($provider->claims()), 'the-nonce');
                self::fail('An unknown key was accepted.');
            } catch (IdTokenException) {
            }
        }
        self::assertCount(2, $provider->requestsTo('GET', self::JWKS_URL));

        $provider->clock->advance(61);
        try {
            $provider->oidc()->verifyIdToken($unknown->sign($provider->claims()), 'the-nonce');
        } catch (IdTokenException) {
        }
        self::assertCount(3, $provider->requestsTo('GET', self::JWKS_URL));
    }

    public function testAMalformedTokenIsRefusedWithATypedException(): void
    {
        $this->expectException(IdTokenException::class);

        (new FakeProvider())->oidc()->verifyIdToken('not.a-jwt', 'the-nonce');
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Http\Json;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Psr\Clock\ClockInterface;

/**
 * ID token validation as OpenID Connect Core section 3.1.3.7 lists it.
 *
 * @internal
 */
final readonly class IdTokenVerifier
{
    /**
     * The asymmetric algorithms the provider signs with. A symmetric one is
     * never accepted even when advertised: it would make the client secret a
     * signing key, so anyone holding it could mint ID tokens.
     */
    private const array SUPPORTED_ALGORITHMS = ['RS256', 'ES256'];

    /**
     * @param list<string> $advertisedAlgorithms
     */
    public function __construct(
        private KeySet $keys,
        private ClockInterface $clock,
        private string $issuer,
        private string $clientId,
        private array $advertisedAlgorithms,
        private int $leeway,
    ) {}

    public function verify(string $jwt, ?string $nonce, ?string $accessToken = null): IdToken
    {
        $segments = explode('.', $jwt);
        if (count($segments) !== 3) {
            throw new IdTokenException('The ID token is not a compact JWS.');
        }

        $header = Json::decodeObject(JWT::urlsafeB64Decode($segments[0]))
            ?? throw new IdTokenException('The ID token header is not a JSON object.');
        $alg = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;
        if (!is_string($alg) || !is_string($kid) || $kid === '') {
            throw new IdTokenException('The ID token header must name its alg and kid.');
        }

        $allowed = array_values(array_intersect(self::SUPPORTED_ALGORITHMS, $this->advertisedAlgorithms));
        if (!in_array($alg, $allowed, true)) {
            throw new IdTokenException(sprintf('The ID token is signed with %s; only %s is accepted.', $alg, $allowed === [] ? 'nothing' : implode(' or ', $allowed)));
        }

        $this->verifySignature($jwt, $kid, $alg);

        $claims = Json::decodeObject(JWT::urlsafeB64Decode($segments[1]))
            ?? throw new IdTokenException('The ID token payload is not a JSON object.');

        $this->verifyClaims($claims, $nonce, $accessToken);

        $subject = $claims['sub'];
        assert(is_string($subject));

        return new IdToken($jwt, $subject, $claims);
    }

    private function verifySignature(string $jwt, string $kid, string $alg): void
    {
        $key = $this->keys->key($kid, [$alg]);

        // firebase/php-jwt reads the time and the leeway from static
        // properties; they are set from the injected clock for this one call
        // and restored, so its exp/nbf/iat checks agree with the ones below.
        $previousTimestamp = JWT::$timestamp;
        $previousLeeway = JWT::$leeway;
        JWT::$timestamp = $this->now();
        JWT::$leeway = $this->leeway;

        try {
            JWT::decode($jwt, [$kid => $key]);
        } catch (ExpiredException $exception) {
            throw new IdTokenException('The ID token has expired: its exp is past, beyond the clock leeway.', 0, $exception);
        } catch (BeforeValidException $exception) {
            throw new IdTokenException('The ID token is not valid yet: its iat or nbf is in the future, beyond the clock leeway.', 0, $exception);
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
            throw new IdTokenException('The ID token signature does not verify: ' . $exception->getMessage(), 0, $exception);
        } finally {
            JWT::$timestamp = $previousTimestamp;
            JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function verifyClaims(array $claims, ?string $nonce, ?string $accessToken): void
    {
        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new IdTokenException(sprintf('The ID token\'s iss is not "%s".', $this->issuer));
        }

        $sub = $claims['sub'] ?? null;
        if (!is_string($sub) || $sub === '') {
            throw new IdTokenException('The ID token has no sub.');
        }

        $aud = $claims['aud'] ?? null;
        $audiences = is_string($aud) ? [$aud] : (is_array($aud) ? $aud : []);
        if (!in_array($this->clientId, $audiences, true)) {
            throw new IdTokenException(sprintf('The ID token\'s aud does not contain this client, "%s".', $this->clientId));
        }
        // Section 3.1.3.7 rule 3: refuse a token that also names audiences
        // the client does not trust. The issuer is first-party, so there is
        // no list of trusted audiences beyond this client.
        foreach ($audiences as $audience) {
            if ($audience !== $this->clientId) {
                throw new IdTokenException(sprintf('The ID token\'s aud names an audience other than this client, "%s".', $this->clientId));
            }
        }

        $azp = $claims['azp'] ?? null;
        if ($azp === null && count($audiences) > 1) {
            throw new IdTokenException('The ID token is meant for several audiences but has no azp.');
        }
        if ($azp !== null && $azp !== $this->clientId) {
            throw new IdTokenException(sprintf('The ID token\'s azp is not this client, "%s".', $this->clientId));
        }

        $now = $this->now();
        $exp = $claims['exp'] ?? null;
        if (!is_int($exp) && !is_float($exp)) {
            throw new IdTokenException('The ID token has no numeric exp.');
        }
        if ($now - $this->leeway >= $exp) {
            throw new IdTokenException('The ID token has expired: its exp is past, beyond the clock leeway.');
        }

        $iat = $claims['iat'] ?? null;
        if (!is_int($iat) && !is_float($iat)) {
            throw new IdTokenException('The ID token has no numeric iat.');
        }
        if ($iat > $now + $this->leeway) {
            throw new IdTokenException('The ID token\'s iat is in the future, beyond the clock leeway.');
        }

        if ($nonce !== null) {
            $claimed = $claims['nonce'] ?? null;
            if (!is_string($claimed) || !hash_equals($nonce, $claimed)) {
                throw new IdTokenException('The ID token\'s nonce is not the one sent with the authorization request.');
            }
        }

        // Section 3.1.3.8: optional in the code flow, but when both are at
        // hand the hash ties the access token to this ID token. RS256 and
        // ES256 both hash with SHA-256.
        $atHash = $claims['at_hash'] ?? null;
        if ($accessToken !== null && $atHash !== null) {
            $expected = JWT::urlsafeB64Encode(substr(hash('sha256', $accessToken, true), 0, 16));
            if (!is_string($atHash) || !hash_equals($expected, $atHash)) {
                throw new IdTokenException('The ID token\'s at_hash does not match the access token.');
            }
        }
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}

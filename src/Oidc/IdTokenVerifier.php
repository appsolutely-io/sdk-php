<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Support\Untrusted;
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

    public function verify(#[\SensitiveParameter] string $jwt, ?string $nonce, #[\SensitiveParameter] ?string $accessToken = null, ?int $maxAge = null): IdToken
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
            throw new IdTokenException(sprintf('The ID token is signed with %s; only %s is accepted.', Untrusted::text($alg), $allowed === [] ? 'nothing' : implode(' or ', $allowed)));
        }

        $this->verifySignature($jwt, $kid, $alg);

        $payload = JWT::urlsafeB64Decode($segments[1]);
        $claims = Json::decodeObject($payload)
            ?? throw new IdTokenException('The ID token payload is not a JSON object.');

        $this->verifyClaims($claims, self::audiences($payload), $nonce, $accessToken, $maxAge);

        $subject = $claims['sub'];
        assert(is_string($subject));

        return new IdToken($jwt, $subject, $claims);
    }

    private function verifySignature(#[\SensitiveParameter] string $jwt, string $kid, string $alg): void
    {
        $key = $this->keys->key($kid, [$alg]);

        // firebase/php-jwt reads the time and the leeway from static
        // properties; they are set from the injected clock for this one call
        // and restored, so its exp/nbf/iat checks agree with the ones below.
        $previousTimestamp = JWT::$timestamp;
        $previousLeeway = JWT::$leeway;
        JWT::$timestamp = $this->now();
        JWT::$leeway = $this->leeway;

        // The library's exceptions are not chained: their stack frames carry
        // the raw token as an argument, which an error tracker would record.
        try {
            JWT::decode($jwt, [$kid => $key]);
        } catch (ExpiredException $exception) {
            throw new IdTokenException('The ID token has expired: its exp is past, beyond the clock leeway.');
        } catch (BeforeValidException $exception) {
            throw new IdTokenException('The ID token is not valid yet: its iat or nbf is in the future, beyond the clock leeway.');
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
            throw new IdTokenException('The ID token signature does not verify: ' . Untrusted::text($exception->getMessage(), Untrusted::MAX_LONG_LENGTH));
        } finally {
            JWT::$timestamp = $previousTimestamp;
            JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * RFC 7519 section 4.1.3: aud is one string or an array of strings. The
     * payload is decoded again with objects kept as objects, because in the
     * associative arrays of the claims {"0": "x"} and ["x"] look alike.
     *
     * @return list<string>
     */
    private static function audiences(string $payload): array
    {
        $decoded = json_decode($payload, false, 64);
        $aud = $decoded instanceof \stdClass && property_exists($decoded, 'aud') ? $decoded->aud : null;

        if (is_string($aud)) {
            return [$aud];
        }

        $audiences = [];
        foreach (is_array($aud) ? $aud : [null] as $audience) {
            if (!is_string($audience)) {
                throw new IdTokenException('The ID token\'s aud must be a string or a list of strings.');
            }
            $audiences[] = $audience;
        }

        return $audiences;
    }

    /**
     * @param array<string, mixed> $claims
     * @param list<string> $audiences
     */
    private function verifyClaims(array $claims, array $audiences, ?string $nonce, #[\SensitiveParameter] ?string $accessToken, ?int $maxAge): void
    {
        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new IdTokenException(sprintf('The ID token\'s iss is not "%s".', $this->issuer));
        }

        $sub = $claims['sub'] ?? null;
        if (!is_string($sub) || $sub === '') {
            throw new IdTokenException('The ID token has no sub.');
        }

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

        // auth_time is a NumericDate whenever it is present (section 2); in any
        // other form it could not be compared with the original authentication
        // on a refresh (section 12.2), so it is refused rather than read as absent.
        if (array_key_exists('auth_time', $claims) && !is_int($claims['auth_time']) && !is_float($claims['auth_time'])) {
            throw new IdTokenException('The ID token\'s auth_time is not a NumericDate.');
        }

        // Section 3.1.3.7 rule 11: when max_age was requested, auth_time is
        // required (section 2) and the authentication it records must be no
        // older than max_age, give or take the clock leeway.
        if ($maxAge !== null) {
            $authTime = $claims['auth_time'] ?? null;
            if (!is_int($authTime) && !is_float($authTime)) {
                throw new IdTokenException('The ID token has no numeric auth_time, which a max_age request requires.');
            }
            if ($authTime + $maxAge + $this->leeway < $now) {
                throw new IdTokenException('The member authenticated longer ago than the requested max_age allows.');
            }
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

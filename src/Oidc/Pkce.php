<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Firebase\JWT\JWT;

/**
 * Proof Key for Code Exchange with the S256 method (RFC 7636).
 *
 * @internal OpenIdClient::authorizationUrl() generates the verifier and keeps it in the AuthorizationRequest
 */
final readonly class Pkce
{
    private function __construct(
        public string $verifier,
        public string $challenge,
    ) {}

    /**
     * 32 random bytes give a 43-character verifier, the minimum length and
     * 256 bits of entropy, as section 4.1 recommends.
     */
    public static function generate(): self
    {
        $verifier = JWT::urlsafeB64Encode(random_bytes(32));

        return new self($verifier, self::challengeFor($verifier));
    }

    public static function challengeFor(string $verifier): string
    {
        return JWT::urlsafeB64Encode(hash('sha256', $verifier, true));
    }
}

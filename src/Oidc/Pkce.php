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
        #[\SensitiveParameter]
        public string $verifier,
        public string $challenge,
    ) {}

    public static function generate(): self
    {
        $verifier = RandomToken::generate();

        return new self($verifier, self::challengeFor($verifier));
    }

    public static function challengeFor(#[\SensitiveParameter] string $verifier): string
    {
        return JWT::urlsafeB64Encode(hash('sha256', $verifier, true));
    }
}

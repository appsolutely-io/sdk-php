<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Firebase\JWT\JWT;

/**
 * @internal
 */
final class RandomToken
{
    /**
     * 32 bytes from the CSPRNG, base64url-encoded without padding: 43
     * characters and 256 bits, past the 128 bits RFC 6749 section 10.10 asks
     * of a value an attacker must guess, and the length and entropy RFC 7636
     * section 4.1 recommends for a PKCE verifier.
     */
    public static function generate(): string
    {
        return JWT::urlsafeB64Encode(random_bytes(32));
    }
}

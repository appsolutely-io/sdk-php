<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

/**
 * The URL to send the member to, and the values the caller must keep
 * (typically in the session) until the member comes back to the redirect URI.
 * They are secrets bound to this one browser: never put them in the URL or a
 * shared store.
 */
final readonly class AuthorizationRequest
{
    public function __construct(
        public string $url,
        public string $redirectUri,
        public string $state,
        public string $nonce,
        #[\SensitiveParameter]
        public string $codeVerifier,
    ) {}
}

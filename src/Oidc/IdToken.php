<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

/**
 * An ID token whose signature and claims have been verified.
 */
final readonly class IdToken
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        public string $raw,
        public string $subject,
        public array $claims,
    ) {}
}

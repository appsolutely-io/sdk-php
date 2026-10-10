<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * The member token a magic link was exchanged for, and what it may do.
 */
final readonly class MagicLinkToken
{
    /** @internal */
    public const string SCHEMA = 'MagicLinkToken';

    /**
     * @param string $token the member's bearer token; pass it to Client::forMember()
     * @param list<string> $abilities what the token may do, such as `me:read`
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        public array $abilities,
        public ?DateTimeImmutable $expiresAt,
        #[\SensitiveParameter]
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            token: $json->string('token'),
            abilities: $json->strings('abilities'),
            expiresAt: $json->optionalTime('expires_at'),
            attributes: $json->all(),
        );
    }

    /**
     * What var_dump() and print_r() show: the token is replaced.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['token' => '[redacted]', 'abilities' => $this->abilities, 'expiresAt' => $this->expiresAt];
    }
}

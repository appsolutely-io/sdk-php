<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OAuthUserInfoEntitlement
{
    /**
     * @param list<array<array-key, mixed>>|null $breakdown
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $key,
        public string $label,
        public int $quantity,
        public ?string $expiresAt,
        public ?string $periodStart,
        public ?string $periodEnd,
        public ?array $breakdown,
        public array $extra,
    ) {}

    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $breakdown = $json->nullableObjects('breakdown');

        return new self($json->string('key'), $json->string('label'), $json->int('quantity'), $json->nullableString('expires_at'), $json->nullableString('period_start'), $json->nullableString('period_end'), $breakdown === null ? null : array_map(static fn(#[\SensitiveParameter] Fields $item): array => $item->extra(), $breakdown), $json->extra());
    }
}

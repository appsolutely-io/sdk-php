<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class OrganisationReplacementSetup implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationReplacementSetup';

    public const string STATUS_OPEN = 'open';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_CLOSING = 'closing';
    public const string STATUS_CLOSED = 'closed';
    public const string STATUS_HELD = 'held';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $status,
        public DateTimeImmutable $firstChargeAt,
        public DateTimeImmutable $expiresAt,
        public ?string $replacementPool,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            status: $json->string('status'),
            firstChargeAt: $json->time('first_charge_at'),
            expiresAt: $json->time('expires_at'),
            replacementPool: $json->nullableString('replacement_pool'),
            extra: $json->extra(),
        );
    }
}

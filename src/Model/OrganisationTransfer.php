<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class OrganisationTransfer implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationTransfer';

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_ACCEPTED = 'accepted';
    public const string STATUS_CANCELLED = 'cancelled';
    public const string STATUS_SUPERSEDED = 'superseded';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $organisation,
        public string $status,
        public ?DateTimeImmutable $acceptedAt,
        public ?DateTimeImmutable $cancelledAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            organisation: $json->string('organisation'),
            status: $json->string('status'),
            acceptedAt: $json->nullableTime('accepted_at'),
            cancelledAt: $json->nullableTime('cancelled_at'),
            extra: $json->extra(),
        );
    }
}

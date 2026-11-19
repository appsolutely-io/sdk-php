<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrderAttempt implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrderAttempt';

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_PAID = 'paid';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_REFUNDED = 'refunded';
    public const string STATUS_UNKNOWN = 'unknown';
    public const string END_REASON_DECLINED = 'declined';
    public const string END_REASON_EXPIRED = 'expired';
    public const string END_REASON_CANCELLED_BY_BUYER = 'cancelled_by_buyer';
    public const string END_REASON_PROVIDER_ERROR = 'provider_error';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $reference,
        public string $status,
        public ?string $endReason,
        public ?string $endReasonLabel,
        public ?\DateTimeImmutable $createdAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            reference: $json->string('reference'),
            status: $json->string('status'),
            endReason: $json->nullableString('end_reason'),
            endReasonLabel: $json->nullableString('end_reason_label'),
            createdAt: $json->nullableTime('created_at'),
            extra: $json->extra(),
        );
    }
}

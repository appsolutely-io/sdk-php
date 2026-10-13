<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A refund, as `refund.requested` and `refund.processed` deliver it. The
 * Site API serves no refund to read, so the delivery is all there is: take
 * the money back once per `$id`, when a delivery first reads
 * `STATUS_REFUNDED`.
 */
final readonly class Refund
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_REFUNDED = 'refunded';

    public const string STATUS_REPORTED = 'reported';

    public const string STATUS_COVERED = 'covered';

    public const string STATUS_DISMISSED = 'dismissed';

    public const string STATUS_FAILED = 'failed';

    /**
     * @param string $id the refund's own id, the same on every delivery about it
     * @param string $reference the refund number a person is shown
     * @param string $order the order number, the id the order goes by
     * @param string $payment the `$reference` of the payment the money comes off, as `order.paid` named it
     * @param int $amount in the minor unit of `$currency`
     * @param string $status one of the STATUS_ constants; only `STATUS_REFUNDED` means the money has gone back
     * @param DateTimeImmutable|null $refundedAt when the money went back, null until it has
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $reference,
        public string $order,
        public string $payment,
        public int $amount,
        public string $currency,
        public string $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $refundedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            reference: $json->string('reference'),
            order: $json->string('order'),
            payment: $json->string('payment'),
            amount: $json->int('amount'),
            currency: $json->string('currency'),
            status: $json->string('status'),
            createdAt: $json->time('created_at'),
            updatedAt: $json->time('updated_at'),
            refundedAt: $json->nullableTime('refunded_at'),
            extra: $json->extra(),
        );
    }
}

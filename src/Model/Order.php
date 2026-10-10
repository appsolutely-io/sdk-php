<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An order. Every amount is an integer count of $currency's minor units
 * (`1999` with `USD` is 19.99); $totalShown is the total as the site
 * displays it.
 */
final readonly class Order
{
    /** @internal */
    public const string SCHEMA = 'Order';

    /**
     * @param string|null $subjectReference the member the order belongs to
     * @param string $mode the mode of money it was placed in
     * @param string $currency ISO 4217
     * @param list<OrderLine> $items
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $id,
        public ?string $subjectReference,
        public ?string $couponCode,
        public ?int $status,
        public string $mode,
        public ?string $subscriptionStartState,
        public ?string $subscriptionStartRefusal,
        public ?string $summary,
        public int $amount,
        public string $currency,
        public int $discountedAmount,
        public int $shippingAmount,
        public int $taxAmount,
        public bool $taxIncluded,
        public int $totalAmount,
        public string $totalShown,
        public ?string $note,
        public ?DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $items,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            subjectReference: $json->optionalString('subject_reference'),
            couponCode: $json->optionalString('coupon_code'),
            status: $json->optionalInt('status'),
            mode: $json->string('mode'),
            subscriptionStartState: $json->optionalString('subscription_start_state'),
            subscriptionStartRefusal: $json->optionalString('subscription_start_refusal'),
            summary: $json->optionalString('summary'),
            amount: $json->int('amount'),
            currency: $json->string('currency'),
            discountedAmount: $json->int('discounted_amount'),
            shippingAmount: $json->int('shipping_amount'),
            taxAmount: $json->int('tax_amount'),
            taxIncluded: $json->bool('tax_included'),
            totalAmount: $json->int('total_amount'),
            totalShown: $json->string('total_shown'),
            note: $json->optionalString('note'),
            createdAt: $json->optionalTime('created_at'),
            updatedAt: $json->time('updated_at'),
            items: array_map(OrderLine::from(...), $json->objects('items')),
            attributes: $json->all(),
        );
    }
}

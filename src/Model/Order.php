<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An order, as an API answer and an `order.*` or `payment.*` delivery both
 * carry it. Every amount is an integer count of `$currency`'s minor units
 * (`1999` with `USD` is 19.99).
 */
final readonly class Order
{
    /** @internal */
    public const string SCHEMA = 'Order';

    /**
     * @param string|null $subjectReference what the order is for beyond the account that placed it
     * @param string|null $status such as `pending`, `paid`, `shipped`, `completed`, `cancelled` or `expired`; a status the site adds later is read as it is sent
     * @param string $mode `production` or `test`: the kind of money behind the order
     * @param string|null $subscriptionStartState how far the subscription this order buys has got; null when it buys none
     * @param string|null $subscriptionStartRefusal why that subscription will not start, when it will not
     * @param string $currency ISO 4217
     * @param bool $taxIncluded whether `$taxAmount` is already part of the other amounts, rather than added to them to reach the total
     * @param string $totalShown what the buyer was told `$totalAmount` is: `final` for the whole charge, `before_provider_tax` for an amount a payment provider then added its own tax to, `priced_by_provider` for a price sent to a provider that ran its own checkout; only on `final` is it what the buyer paid
     * @param DateTimeImmutable|null $updatedAt null when the site holds no update time for the order
     * @param list<OrderLine> $items
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $subjectReference,
        public ?string $couponCode,
        public ?string $status,
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
        public ?DateTimeImmutable $updatedAt,
        public array $items,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            subjectReference: $json->nullableString('subject_reference'),
            couponCode: $json->nullableString('coupon_code'),
            status: $json->nullableString('status'),
            mode: $json->string('mode'),
            subscriptionStartState: $json->nullableString('subscription_start_state'),
            subscriptionStartRefusal: $json->nullableString('subscription_start_refusal'),
            summary: $json->nullableString('summary'),
            amount: $json->int('amount'),
            currency: $json->string('currency'),
            discountedAmount: $json->int('discounted_amount'),
            shippingAmount: $json->int('shipping_amount'),
            taxAmount: $json->int('tax_amount'),
            taxIncluded: $json->bool('tax_included'),
            totalAmount: $json->int('total_amount'),
            totalShown: $json->string('total_shown'),
            note: $json->nullableString('note'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            items: array_map(OrderLine::from(...), $json->objects('items')),
            extra: $json->extra(),
        );
    }
}

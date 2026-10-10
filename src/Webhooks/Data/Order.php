<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * An order as the site's REST API serves it. Every amount is in the minor
 * unit of `$currency`.
 */
final readonly class Order
{
    /**
     * @param string|null $subjectReference what the order is for beyond the account that placed it
     * @param string|null $status `pending`, `paid`, `shipped`, `completed`, `cancelled` or `expired`
     * @param string $mode `production` or `test`: the kind of money behind the order
     * @param string|null $subscriptionStartState how far the subscription this order buys has got; null when it buys none
     * @param string|null $subscriptionStartRefusal why that subscription will not start, when it will not
     * @param bool $taxIncluded whether `$taxAmount` is already part of the other amounts
     * @param string $totalShown what the buyer was told `$totalAmount` is, such as `final`
     * @param list<OrderLine>|null $items null when the site did not describe them
     * @param array<string, mixed> $extra fields the site sent that this class does not name
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
        public ?array $items,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        $items = $fields->nullableObjects('items');

        return new self(
            $fields->string('id'),
            $fields->nullableString('subject_reference'),
            $fields->nullableString('coupon_code'),
            $fields->nullableString('status'),
            $fields->string('mode'),
            $fields->nullableString('subscription_start_state'),
            $fields->nullableString('subscription_start_refusal'),
            $fields->nullableString('summary'),
            $fields->int('amount'),
            $fields->string('currency'),
            $fields->int('discounted_amount'),
            $fields->int('shipping_amount'),
            $fields->int('tax_amount'),
            $fields->bool('tax_included'),
            $fields->int('total_amount'),
            $fields->string('total_shown'),
            $fields->nullableString('note'),
            $fields->nullableTime('created_at'),
            $fields->nullableTime('updated_at'),
            $items === null ? null : array_map(OrderLine::read(...), $items),
            $fields->extra(),
        );
    }
}

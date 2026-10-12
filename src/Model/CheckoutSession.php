<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A hosted checkout the member is sent to, and where it stands.
 */
final readonly class CheckoutSession
{
    /** @internal */
    public const string SCHEMA = 'CheckoutSession';

    /**
     * @param string $url where to send the member
     * @param string $status `open`, `expired`, `awaiting_payment`, `payment_overdue`, `complete` or `canceled`
     * @param string $mode `production` or `test`
     * @param list<CheckoutSessionLine> $lines
     * @param string|null $promotionCode the code the checkout was started with
     * @param string|null $orderReference the reference of the order the checkout placed, or null before it placed one
     * @param list<SubscriptionStillBilling> $mayStillBill subscriptions another party still bills, which this purchase does not stop; empty unless the site found some
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $url,
        public string $status,
        public DateTimeImmutable $expiresAt,
        public string $mode,
        public array $lines,
        public ?string $promotionCode,
        public string $successUrl,
        public string $cancelUrl,
        public ?string $orderReference,
        public array $mayStillBill,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $mayStillBillList = $json->nullableObjects('may_still_bill');

        return new self(
            id: $json->string('id'),
            url: $json->string('url'),
            status: $json->string('status'),
            expiresAt: $json->time('expires_at'),
            mode: $json->string('mode'),
            lines: array_map(CheckoutSessionLine::from(...), $json->objects('lines')),
            promotionCode: $json->nullableString('promotion_code'),
            successUrl: $json->string('success_url'),
            cancelUrl: $json->string('cancel_url'),
            orderReference: $json->nullableObject('order')?->string('reference'),
            mayStillBill: $mayStillBillList === null ? [] : array_map(SubscriptionStillBilling::from(...), $mayStillBillList),
            extra: $json->extra(),
        );
    }
}

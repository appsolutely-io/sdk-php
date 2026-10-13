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

    public const string STATUS_OPEN = 'open';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const string STATUS_PAYMENT_OVERDUE = 'payment_overdue';

    public const string STATUS_COMPLETE = 'complete';

    public const string STATUS_CANCELED = 'canceled';

    public const string MODE_PRODUCTION = 'production';

    public const string MODE_TEST = 'test';

    /**
     * @param string $url where to send the member
     * @param string $status one of the STATUS_ constants
     * @param string $mode one of the MODE_ constants
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

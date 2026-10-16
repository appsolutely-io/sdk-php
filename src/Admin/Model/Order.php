<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Model;

use Appsolutely\Sdk\Model\Fields;
use DateTimeImmutable;

/**
 * An order as the Administrator API answers it; amounts are integer minor units of its currency.
 */
final readonly class Order implements AdminModel
{
    /** @internal */
    public const string SCHEMA = 'Order';

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_PAID = 'paid';
    public const string STATUS_SHIPPED = 'shipped';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_CANCELLED = 'cancelled';
    public const string STATUS_EXPIRED = 'expired';
    public const string ORIGIN_CUSTOMER = 'customer';
    public const string ORIGIN_SYSTEM = 'system';
    public const string MODE_PRODUCTION = 'production';
    public const string MODE_TEST = 'test';

    /**
     * @param string $id its order number
     * @param ?string $status one of the `STATUS_*` constants
     * @param ?string $origin `ORIGIN_CUSTOMER` or `ORIGIN_SYSTEM`
     * @param ?string $mode `MODE_PRODUCTION` or `MODE_TEST`
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $reference,
        public ?string $status,
        public ?string $origin,
        public ?string $summary,
        public ?string $email,
        public ?string $billingCountry,
        public ?string $currency,
        public ?int $totalAmount,
        public ?int $amount,
        public ?int $discountedAmount,
        public ?int $shippingAmount,
        public ?int $taxAmount,
        public ?bool $taxIncluded,
        public ?string $mode,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): static
    {
        return new self(
            id: $json->string('id'),
            reference: $json->nullableString('reference'),
            status: $json->nullableString('status'),
            origin: $json->nullableString('origin'),
            summary: $json->nullableString('summary'),
            email: $json->nullableString('email'),
            billingCountry: $json->nullableString('billing_country'),
            currency: $json->nullableString('currency'),
            totalAmount: $json->nullableInt('total_amount'),
            amount: $json->nullableInt('amount'),
            discountedAmount: $json->nullableInt('discounted_amount'),
            shippingAmount: $json->nullableInt('shipping_amount'),
            taxAmount: $json->nullableInt('tax_amount'),
            taxIncluded: $json->nullableBool('tax_included'),
            mode: $json->nullableString('mode'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One of the member's own subscriptions, as the member manages it.
 */
final readonly class MemberSubscription
{
    /** @internal */
    public const string SCHEMA = 'MemberSubscription';

    public const string BILLED_BY_SITE = 'site';

    public const string BILLED_BY_APP_STORE = 'app_store';

    public const string BILLED_BY_GOOGLE_PLAY = 'google_play';

    /**
     * @param string|null $product the product's slug
     * @param string|null $sku the slug of the plan it renews on
     * @param string|null $pendingSku the plan it changes to at its next renewal
     * @param int|null $pendingQuantity the quantity it changes to at its next renewal
     * @param string $billedBy who bills it: one of the BILLED_BY_ constants
     * @param string|null $manageUrl where a store bills it, the store's page for managing it
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $product,
        public ?string $sku,
        public string $status,
        public int $quantity,
        public string $currency,
        public ?DateTimeImmutable $currentPeriodEnd,
        public bool $cancelAtPeriodEnd,
        public ?string $pendingSku,
        public ?int $pendingQuantity,
        public string $billedBy,
        public ?string $manageUrl,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            product: $json->nullableString('product'),
            sku: $json->nullableString('sku'),
            status: $json->string('status'),
            quantity: $json->int('quantity'),
            currency: $json->string('currency'),
            currentPeriodEnd: $json->nullableTime('current_period_end'),
            cancelAtPeriodEnd: $json->bool('cancel_at_period_end'),
            pendingSku: $json->nullableString('pending_sku'),
            pendingQuantity: $json->nullableInt('pending_quantity'),
            billedBy: $json->string('billed_by'),
            manageUrl: $json->nullableString('manage_url'),
            extra: $json->extra(),
        );
    }
}

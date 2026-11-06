<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What an app passes to a store's purchase sheet to buy a product the site sells there.
 */
final readonly class StorePurchaseIntent implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'StorePurchaseIntent';

    /**
     * @param string $store `app_store` or `google_play`
     * @param string $storeProductId the product's id in the store
     * @param string $accountToken the token to pass as the store purchase's account identifier, which ties the purchase to the member
     * @param string|null $replacesPurchaseToken on Google Play, the purchase the new one replaces
     * @param list<SubscriptionSource> $mayStillBill the member's subscriptions another party still bills, which this purchase does not stop
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $store,
        public string $storeProductId,
        public string $accountToken,
        public ?string $replacesPurchaseToken,
        public array $mayStillBill,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            store: $json->string('store'),
            storeProductId: $json->string('store_product_id'),
            accountToken: $json->string('account_token'),
            replacesPurchaseToken: $json->nullableString('replaces_purchase_token'),
            mayStillBill: array_map(SubscriptionSource::from(...), $json->objects('may_still_bill')),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\StorePurchaseIntent;
use Appsolutely\Sdk\Model\StorePurchaseTransaction;

/**
 * Purchases the member makes in an app store.
 */
final readonly class StorePurchases
{
    /** @internal obtain it from Client::forMember()->api()->me()->storePurchases() */
    public function __construct(private Caller $caller) {}

    /**
     * What to pass the store's purchase sheet to buy a product.
     *
     * @param 'app_store'|'google_play' $store
     * @param string $product the product's slug
     * @param string|null $sku the plan's slug, for a product with several
     */
    #[Endpoint(Operation::IntentsMeStorePurchase)]
    public function intent(string $store, string $product, ?string $sku = null): StorePurchaseIntent
    {
        return $this->caller->read(Operation::IntentsMeStorePurchase, StorePurchaseIntent::SCHEMA, StorePurchaseIntent::from(...), body: ['store' => $store, 'product' => $product, 'sku' => $sku]);
    }

    /**
     * Hands the site a purchase the store reported: an App Store purchase by
     * its signed transaction, a Google Play one by its package, product and
     * purchase token.
     *
     * @param 'app_store'|'google_play' $store
     * @param 'member'|'automatic' $initiation whether the member bought it just now, or the store renewed or restored it
     */
    #[Endpoint(Operation::TransactionsMeStorePurchase)]
    public function transaction(
        string $store,
        string $initiation,
        #[\SensitiveParameter]
        ?string $signedTransaction = null,
        ?string $packageName = null,
        ?string $productId = null,
        #[\SensitiveParameter]
        ?string $purchaseToken = null,
    ): StorePurchaseTransaction {
        return $this->caller->read(Operation::TransactionsMeStorePurchase, StorePurchaseTransaction::SCHEMA, StorePurchaseTransaction::from(...), body: array_filter([
            'store' => $store,
            'initiation' => $initiation,
            'signed_transaction' => $signedTransaction,
            'package_name' => $packageName,
            'product_id' => $productId,
            'purchase_token' => $purchaseToken,
        ], static fn(?string $value): bool => $value !== null));
    }
}

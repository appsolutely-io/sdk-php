<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\StorePurchaseIntent;
use Appsolutely\Sdk\Model\StorePurchaseTransaction;

/**
 * Purchases a member makes in an app store, as the backend hands them on.
 */
final readonly class StorePurchases
{
    /** @internal obtain it from Client::backend()->storePurchases() */
    public function __construct(private Caller $caller) {}

    /**
     * What to pass the store's purchase sheet to buy a product for a member.
     *
     * @param string $subject the member's public name, the `sub` its ID token carries
     * @param 'app_store'|'google_play' $store
     * @param string $product the product's slug
     * @param string|null $sku the plan's slug, for a product with several
     */
    #[Endpoint(Operation::IntentsStorePurchase)]
    public function intent(string $subject, string $store, string $product, ?string $sku = null): StorePurchaseIntent
    {
        return $this->caller->read(Operation::IntentsStorePurchase, StorePurchaseIntent::class, body: ['subject' => $subject, 'store' => $store, 'product' => $product, 'sku' => $sku]);
    }

    /**
     * Hands the site a purchase the store reported for a member: an App
     * Store purchase by its signed transaction, a Google Play one by its
     * package, product and purchase token.
     *
     * @param string $subject the member's public name, the `sub` its ID token carries
     * @param 'app_store'|'google_play' $store
     * @param 'member'|'automatic' $initiation whether the member bought it just now, or the store renewed or restored it
     */
    #[Endpoint(Operation::TransactionsStorePurchase)]
    public function transaction(
        string $subject,
        string $store,
        string $initiation,
        #[\SensitiveParameter]
        ?string $signedTransaction = null,
        ?string $packageName = null,
        ?string $productId = null,
        #[\SensitiveParameter]
        ?string $purchaseToken = null,
    ): StorePurchaseTransaction {
        return $this->caller->read(Operation::TransactionsStorePurchase, StorePurchaseTransaction::class, body: array_filter([
            'subject' => $subject,
            'store' => $store,
            'initiation' => $initiation,
            'signed_transaction' => $signedTransaction,
            'package_name' => $packageName,
            'product_id' => $productId,
            'purchase_token' => $purchaseToken,
        ], static fn(?string $value): bool => $value !== null));
    }
}

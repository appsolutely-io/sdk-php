<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\BillingEntry as BillingEntryModel;

/**
 * Where the member manages their billing.
 */
final readonly class BillingEntry
{
    /** @internal obtain it from Client::forMember()->api()->me()->billingEntry() */
    public function __construct(private Caller $caller) {}

    /**
     * The entry for the store the app was installed from.
     *
     * @param 'app_store'|'google_play'|'direct' $store
     * @param string|null $storefront the store's two-letter region; required unless $store is `direct`
     */
    #[Endpoint(Operation::GetMeBillingEntry)]
    public function get(string $store, ?string $storefront = null): BillingEntryModel
    {
        return $this->caller->read(Operation::GetMeBillingEntry, BillingEntryModel::SCHEMA, BillingEntryModel::from(...), query: ['store' => $store, 'storefront' => $storefront]);
    }
}

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
     * @param string|null $storeToken the token the store handed the app for this tap, once an entry answered `needs_store_token`
     * @param 'ios'|'ipados'|'macos'|'tvos'|'visionos'|'watchos'|null $platform the Apple platform the app runs on, kept with the tap
     * @param string|null $osVersion the system's version, such as `17.4`: an entry the system does not support is answered unavailable
     */
    #[Endpoint(Operation::GetMeBillingEntry)]
    public function get(string $store, ?string $storefront = null, #[\SensitiveParameter] ?string $storeToken = null, ?string $platform = null, ?string $osVersion = null): BillingEntryModel
    {
        return $this->caller->read(Operation::GetMeBillingEntry, BillingEntryModel::class, query: [
            'store' => $store,
            'storefront' => $storefront,
            'store_token' => $storeToken,
            'platform' => $platform,
            'os_version' => $osVersion,
        ]);
    }
}

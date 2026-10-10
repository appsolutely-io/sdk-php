<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\Entitlement;

/**
 * What the member currently holds the right to.
 */
final readonly class Entitlements
{
    /** @internal obtain it from Client::forMember()->api()->me()->entitlements() */
    public function __construct(private Caller $caller) {}

    /**
     * The member's live entitlements, in one page that never has a cursor.
     *
     * @return Page<Entitlement>
     */
    #[Endpoint(Operation::ListMeEntitlements)]
    public function list(): Page
    {
        return $this->caller->onlyPage(Operation::ListMeEntitlements, Entitlement::SCHEMA, Entitlement::from(...));
    }
}

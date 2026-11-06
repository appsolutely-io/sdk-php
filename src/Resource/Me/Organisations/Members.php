<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\OrganisationMembership;

/**
 * An organisation's members, as its owner or an admin manages them.
 */
final readonly class Members
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return Paginator<OrganisationMembership> */
    #[Endpoint(Operation::ListMeOrganisationMembers)]
    public function list(string $organisation, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationMembers, OrganisationMembership::class, [], $limit, ['organisation' => $organisation]);
    }

    /**
     * Removes a member from the organisation; their seat, if any, is freed.
     */
    #[Endpoint(Operation::RemoveMeOrganisationMember)]
    public function remove(string $organisation, string $membership, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->caller->send(Operation::RemoveMeOrganisationMember, ['organisation' => $organisation, 'membership' => $membership], idempotencyKey: $idempotencyKey);
    }

    /**
     * Makes a member an admin of the organisation.
     */
    #[Endpoint(Operation::PromoteMeOrganisationMember)]
    public function promote(string $organisation, string $membership, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->caller->send(Operation::PromoteMeOrganisationMember, ['organisation' => $organisation, 'membership' => $membership], idempotencyKey: $idempotencyKey);
    }

    /**
     * Makes an admin of the organisation a plain member again.
     */
    #[Endpoint(Operation::DemoteMeOrganisationMember)]
    public function demote(string $organisation, string $membership, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->caller->send(Operation::DemoteMeOrganisationMember, ['organisation' => $organisation, 'membership' => $membership], idempotencyKey: $idempotencyKey);
    }
}

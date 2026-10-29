<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class Organisations
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return \Appsolutely\Sdk\Api\Paginator<\Appsolutely\Sdk\Model\Organisation> */
    #[Endpoint(Operation::ListMeOrganisations)]
    public function list(int $limit = \Appsolutely\Sdk\Api\SiteApi::DEFAULT_LIMIT): \Appsolutely\Sdk\Api\Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisations, \Appsolutely\Sdk\Model\Organisation::class, [], $limit, []);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\Organisation> */
    #[Endpoint(Operation::CreateMeOrganisation)]
    public function create(string $name, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisation, \Appsolutely\Sdk\Model\Organisation::class, [], ['name' => $name], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\Paginator<\Appsolutely\Sdk\Model\OrganisationChange> */
    #[Endpoint(Operation::ListMeOrganisationChanges)]
    public function changes(string $organisation, int $limit = \Appsolutely\Sdk\Api\SiteApi::DEFAULT_LIMIT): \Appsolutely\Sdk\Api\Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationChanges, \Appsolutely\Sdk\Model\OrganisationChange::class, [], $limit, ['organisation' => $organisation]);
    }

    #[Endpoint(Operation::LeaveMeOrganisation)]
    public function leave(string $organisation, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\ApiResponse
    {
        return $this->caller->send(Operation::LeaveMeOrganisation, ['organisation' => $organisation], body: [], idempotencyKey: $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::DissolveMeOrganisation)]
    public function dissolve(string $organisation, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::DissolveMeOrganisation, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation], [], $idempotencyKey);
    }
    public function invitations(): \Appsolutely\Sdk\Resource\Me\Organisations\Invitations
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations\Invitations($this->caller);
    }
    public function roles(): \Appsolutely\Sdk\Resource\Me\Organisations\Roles
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations\Roles($this->caller);
    }
    public function seats(): \Appsolutely\Sdk\Resource\Me\Organisations\Seats
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations\Seats($this->caller);
    }
    public function transfers(): \Appsolutely\Sdk\Resource\Me\Organisations\Transfers
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations\Transfers($this->caller);
    }
    public function billing(): \Appsolutely\Sdk\Resource\Me\Organisations\Billing
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations\Billing($this->caller);
    }
    public function recoveryBindings(): \Appsolutely\Sdk\Resource\Me\Organisations\RecoveryBindings
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations\RecoveryBindings($this->caller);
    }
    public function replacements(): \Appsolutely\Sdk\Resource\Me\Organisations\Replacements
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations\Replacements($this->caller);
    }
}

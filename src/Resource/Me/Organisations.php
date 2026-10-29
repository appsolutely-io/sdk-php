<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\Organisation;
use Appsolutely\Sdk\Model\OrganisationBillingOperation;
use Appsolutely\Sdk\Model\OrganisationChange;
use Appsolutely\Sdk\Resource\Me\Organisations\Billing;
use Appsolutely\Sdk\Resource\Me\Organisations\Invitations;
use Appsolutely\Sdk\Resource\Me\Organisations\RecoveryBindings;
use Appsolutely\Sdk\Resource\Me\Organisations\Replacements;
use Appsolutely\Sdk\Resource\Me\Organisations\Roles;
use Appsolutely\Sdk\Resource\Me\Organisations\Seats;
use Appsolutely\Sdk\Resource\Me\Organisations\Transfers;

/**
 * The member's organisations and their lifecycle operations.
 */
final readonly class Organisations
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return Paginator<Organisation> */
    #[Endpoint(Operation::ListMeOrganisations)]
    public function list(int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisations, Organisation::class, [], $limit, []);
    }

    /** @return KeyedResult<Organisation> */
    #[Endpoint(Operation::CreateMeOrganisation)]
    public function create(string $name, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisation, Organisation::class, [], ['name' => $name], $idempotencyKey);
    }

    /** @return Paginator<OrganisationChange> */
    #[Endpoint(Operation::ListMeOrganisationChanges)]
    public function changes(string $organisation, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationChanges, OrganisationChange::class, [], $limit, ['organisation' => $organisation]);
    }

    #[Endpoint(Operation::LeaveMeOrganisation)]
    public function leave(string $organisation, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->caller->send(Operation::LeaveMeOrganisation, ['organisation' => $organisation], body: [], idempotencyKey: $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::DissolveMeOrganisation)]
    public function dissolve(string $organisation, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::DissolveMeOrganisation, OrganisationBillingOperation::class, ['organisation' => $organisation], [], $idempotencyKey);
    }

    public function invitations(): Invitations
    {
        return new Invitations($this->caller);
    }

    public function roles(): Roles
    {
        return new Roles($this->caller);
    }

    public function seats(): Seats
    {
        return new Seats($this->caller);
    }

    public function transfers(): Transfers
    {
        return new Transfers($this->caller);
    }

    public function billing(): Billing
    {
        return new Billing($this->caller);
    }

    public function recoveryBindings(): RecoveryBindings
    {
        return new RecoveryBindings($this->caller);
    }

    public function replacements(): Replacements
    {
        return new Replacements($this->caller);
    }
}

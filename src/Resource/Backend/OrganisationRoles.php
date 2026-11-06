<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\OrganisationRoleDefinition;
use Appsolutely\Sdk\Model\OrganisationRoles as OrganisationRoleAssignments;
use Appsolutely\Sdk\Model\ScopedRole;

/**
 * Organisation role assignments and definitions called with backend credentials.
 */
final readonly class OrganisationRoles
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetBackendOrganisationRole)]
    public function get(string $organisation, string $membership): OrganisationRoleAssignments
    {
        return $this->caller->read(Operation::GetBackendOrganisationRole, OrganisationRoleAssignments::class, ['organisation' => $organisation, 'membership' => $membership]);
    }

    #[Endpoint(Operation::AssignBackendOrganisationRole)]
    public function assign(string $organisation, string $membership, #[\SensitiveParameter] string $key): OrganisationRoleAssignments
    {
        return $this->caller->read(Operation::AssignBackendOrganisationRole, OrganisationRoleAssignments::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key]);
    }

    #[Endpoint(Operation::RevokeBackendOrganisationRole)]
    public function revoke(string $organisation, string $membership, #[\SensitiveParameter] string $key): OrganisationRoleAssignments
    {
        return $this->caller->read(Operation::RevokeBackendOrganisationRole, OrganisationRoleAssignments::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key]);
    }

    /** @return Page<OrganisationRoleDefinition> */
    #[Endpoint(Operation::ListBackendOrganisationRoles)]
    public function list(string $organisation): Page
    {
        return $this->caller->onlyPage(Operation::ListBackendOrganisationRoles, OrganisationRoleDefinition::class, path: ['organisation' => $organisation]);
    }

    #[Endpoint(Operation::CreateBackendOrganisationRole)]
    public function create(string $organisation, #[\SensitiveParameter] string $key): ScopedRole
    {
        return $this->caller->read(Operation::CreateBackendOrganisationRole, ScopedRole::class, ['organisation' => $organisation], body: ['key' => $key]);
    }

    #[Endpoint(Operation::RetireBackendOrganisationRole)]
    public function retire(string $organisation, #[\SensitiveParameter] string $key): ScopedRole
    {
        return $this->caller->read(Operation::RetireBackendOrganisationRole, ScopedRole::class, ['organisation' => $organisation, 'key' => $key]);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class OrganisationRoles
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetBackendOrganisationRole)]
    public function get(string $organisation, string $membership): \Appsolutely\Sdk\Model\OrganisationRoles
    {
        return $this->caller->read(Operation::GetBackendOrganisationRole, \Appsolutely\Sdk\Model\OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership]);
    }

    #[Endpoint(Operation::AssignBackendOrganisationRole)]
    public function assign(string $organisation, string $membership, #[\SensitiveParameter] string $key): \Appsolutely\Sdk\Model\OrganisationRoles
    {
        return $this->caller->read(Operation::AssignBackendOrganisationRole, \Appsolutely\Sdk\Model\OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key]);
    }

    #[Endpoint(Operation::RevokeBackendOrganisationRole)]
    public function revoke(string $organisation, string $membership, #[\SensitiveParameter] string $key): \Appsolutely\Sdk\Model\OrganisationRoles
    {
        return $this->caller->read(Operation::RevokeBackendOrganisationRole, \Appsolutely\Sdk\Model\OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key]);
    }

    /** @return \Appsolutely\Sdk\Api\Page<\Appsolutely\Sdk\Model\OrganisationRoleDefinition> */
    #[Endpoint(Operation::ListBackendOrganisationRoles)]
    public function list(string $organisation): \Appsolutely\Sdk\Api\Page
    {
        return $this->caller->onlyPage(Operation::ListBackendOrganisationRoles, \Appsolutely\Sdk\Model\OrganisationRoleDefinition::class, path: ['organisation' => $organisation]);
    }

    #[Endpoint(Operation::CreateBackendOrganisationRole)]
    public function create(string $organisation, #[\SensitiveParameter] string $key): \Appsolutely\Sdk\Model\ScopedRole
    {
        return $this->caller->read(Operation::CreateBackendOrganisationRole, \Appsolutely\Sdk\Model\ScopedRole::class, ['organisation' => $organisation], body: ['key' => $key]);
    }

    #[Endpoint(Operation::RetireBackendOrganisationRole)]
    public function retire(string $organisation, #[\SensitiveParameter] string $key): \Appsolutely\Sdk\Model\ScopedRole
    {
        return $this->caller->read(Operation::RetireBackendOrganisationRole, \Appsolutely\Sdk\Model\ScopedRole::class, ['organisation' => $organisation, 'key' => $key]);
    }
}

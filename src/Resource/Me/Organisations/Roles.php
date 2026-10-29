<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class Roles
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetOrganisationRole)]
    public function get(string $organisation, string $membership): \Appsolutely\Sdk\Model\OrganisationRoles
    {
        return $this->caller->read(Operation::GetOrganisationRole, \Appsolutely\Sdk\Model\OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership]);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationRoles> */
    #[Endpoint(Operation::AssignOrganisationRole)]
    public function assign(string $organisation, string $membership, #[\SensitiveParameter] string $key, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::AssignOrganisationRole, \Appsolutely\Sdk\Model\OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key], [], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationRoles> */
    #[Endpoint(Operation::RevokeOrganisationRole)]
    public function revoke(string $organisation, string $membership, #[\SensitiveParameter] string $key, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::RevokeOrganisationRole, \Appsolutely\Sdk\Model\OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key], [], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\Page<\Appsolutely\Sdk\Model\OrganisationRoleDefinition> */
    #[Endpoint(Operation::ListOrganisationRoles)]
    public function list(string $organisation): \Appsolutely\Sdk\Api\Page
    {
        return $this->caller->onlyPage(Operation::ListOrganisationRoles, \Appsolutely\Sdk\Model\OrganisationRoleDefinition::class, path: ['organisation' => $organisation]);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\OrganisationRoleDefinition;
use Appsolutely\Sdk\Model\OrganisationRoles;

/**
 * Organisation role assignments managed by the signed-in member.
 */
final readonly class Roles
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetOrganisationRole)]
    public function get(string $organisation, string $membership): OrganisationRoles
    {
        return $this->caller->read(Operation::GetOrganisationRole, OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership]);
    }

    /** @return KeyedResult<OrganisationRoles> */
    #[Endpoint(Operation::AssignOrganisationRole)]
    public function assign(string $organisation, string $membership, #[\SensitiveParameter] string $key, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::AssignOrganisationRole, OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key], null, $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationRoles> */
    #[Endpoint(Operation::RevokeOrganisationRole)]
    public function revoke(string $organisation, string $membership, #[\SensitiveParameter] string $key, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::RevokeOrganisationRole, OrganisationRoles::class, ['organisation' => $organisation, 'membership' => $membership, 'key' => $key], [], $idempotencyKey);
    }

    /** @return Page<OrganisationRoleDefinition> */
    #[Endpoint(Operation::ListOrganisationRoles)]
    public function list(string $organisation): Page
    {
        return $this->caller->onlyPage(Operation::ListOrganisationRoles, OrganisationRoleDefinition::class, path: ['organisation' => $organisation]);
    }
}

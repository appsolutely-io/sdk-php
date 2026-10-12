<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\RolesHeld;

/**
 * Which members hold which roles, in the backend's mode.
 */
final readonly class RoleAssignments
{
    /** @internal obtain it from Client::backend()->roleAssignments() */
    public function __construct(private Caller $caller) {}

    /**
     * Every member holding a role, or the one role named.
     *
     * @return Paginator<RolesHeld>
     */
    #[Endpoint(Operation::ListRoleAssignments)]
    public function list(?string $role = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListRoleAssignments, RolesHeld::SCHEMA, RolesHeld::from(...), ['role' => $role], $limit);
    }

    /**
     * @param string $subject the member's public name, the `sub` its ID token carries
     */
    #[Endpoint(Operation::GetRoleAssignment)]
    public function get(string $subject): RolesHeld
    {
        return $this->caller->read(Operation::GetRoleAssignment, RolesHeld::SCHEMA, RolesHeld::from(...), ['sub' => $subject]);
    }

    /**
     * Gives the member the role; giving one already held changes nothing.
     *
     * @param string $subject the member's public name, the `sub` its ID token carries
     */
    #[Endpoint(Operation::AssignRoleAssignment)]
    public function assign(string $subject, string $role): RolesHeld
    {
        return $this->caller->read(Operation::AssignRoleAssignment, RolesHeld::SCHEMA, RolesHeld::from(...), ['sub' => $subject, 'key' => $role]);
    }

    /**
     * Takes the role back; taking one not held changes nothing.
     *
     * @param string $subject the member's public name, the `sub` its ID token carries
     */
    #[Endpoint(Operation::RevokeRoleAssignment)]
    public function revoke(string $subject, string $role): void
    {
        $this->caller->send(Operation::RevokeRoleAssignment, ['sub' => $subject, 'key' => $role]);
    }
}

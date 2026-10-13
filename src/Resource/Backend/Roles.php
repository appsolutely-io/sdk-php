<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\Role;

/**
 * The roles the backend defines, in its mode, for members to hold.
 */
final readonly class Roles
{
    /** @internal obtain it from Client::backend()->roles() */
    public function __construct(private Caller $caller) {}

    /**
     * Every role, in one page that never has a cursor.
     *
     * @return Page<Role>
     */
    #[Endpoint(Operation::ListRoles)]
    public function list(): Page
    {
        return $this->caller->onlyPage(Operation::ListRoles, Role::class);
    }

    /**
     * Defines a role; defining one it already has answers that one.
     */
    #[Endpoint(Operation::CreateRole)]
    public function create(string $key): Role
    {
        return $this->caller->read(Operation::CreateRole, Role::class, body: ['key' => $key]);
    }

    /**
     * Retires a role: it can no longer be assigned.
     */
    #[Endpoint(Operation::RetireRole)]
    public function retire(string $key): Role
    {
        return $this->caller->read(Operation::RetireRole, Role::class, ['key' => $key]);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\ReturnTarget;

/**
 * The addresses a checkout or a visit to the billing pages may send a member
 * back to, in the backend's mode.
 */
final readonly class ReturnTargets
{
    /** @internal obtain it from Client::backend()->returnTargets() */
    public function __construct(private Caller $caller) {}

    /**
     * Every return address, in one page that never has a cursor.
     *
     * @return Page<ReturnTarget>
     */
    #[Endpoint(Operation::ListReturnTargets)]
    public function list(): Page
    {
        return $this->caller->onlyPage(Operation::ListReturnTargets, ReturnTarget::class);
    }

    /**
     * Adds an address on a host the site has verified; adding one it already
     * has answers that one.
     */
    #[Endpoint(Operation::CreateReturnTarget)]
    public function create(string $url): ReturnTarget
    {
        return $this->caller->read(Operation::CreateReturnTarget, ReturnTarget::class, body: ['url' => $url]);
    }

    #[Endpoint(Operation::DeleteReturnTarget)]
    public function delete(string $id): void
    {
        $this->caller->send(Operation::DeleteReturnTarget, ['target' => $id]);
    }
}

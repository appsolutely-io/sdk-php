<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\AccountState;

/**
 * The state of every account the site handed the app a name for, in the
 * backend's mode: what a reconciler compares with the deliveries it kept.
 */
final readonly class AccountStates
{
    /** @internal obtain it from Client::backend()->accountStates() */
    public function __construct(private Caller $caller) {}

    /**
     * @return Paginator<AccountState>
     */
    #[Endpoint(Operation::ListBackendEntitlements)]
    public function list(int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListBackendEntitlements, AccountState::SCHEMA, AccountState::from(...), [], $limit);
    }

    /**
     * One account's state; a subject the site never handed out is answered
     * as AccountState::STATUS_UNKNOWN rather than refused.
     *
     * @param string $subject the account's public name, the `sub` its ID token carries
     */
    #[Endpoint(Operation::GetBackendEntitlement)]
    public function get(string $subject): AccountState
    {
        return $this->caller->read(Operation::GetBackendEntitlement, AccountState::SCHEMA, AccountState::from(...), ['sub' => $subject]);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\AccountState;

/**
 * The current state of members' accounts, as the administrator.
 */
final readonly class AccountStates
{
    /** @internal obtain it from Client::api()->accountStates() */
    public function __construct(private Caller $caller) {}

    /**
     * The state of each member named, in one page that never has a cursor:
     * the list is as long as the question.
     *
     * @param non-empty-list<string> $subjects the members' ids
     * @return Page<AccountState>
     */
    #[Endpoint(Operation::ListAccountStates)]
    public function list(array $subjects): Page
    {
        return $this->caller->onlyPage(Operation::ListAccountStates, AccountState::SCHEMA, AccountState::from(...), ['subjects' => $subjects]);
    }
}

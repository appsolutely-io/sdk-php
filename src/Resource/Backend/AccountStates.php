<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\AccountState;
use Appsolutely\Sdk\Model\ListedAccountStatePage;
use DateTimeInterface;

/**
 * The state of every account the site handed the app a name for, in the
 * backend's mode: what a reconciler compares with the deliveries it kept.
 */
final readonly class AccountStates
{
    /** @internal obtain it from Client::backend()->accountStates() */
    public function __construct(private Caller $caller) {}

    /**
     * One walk through the accounts, a page at a time, ordered by when each
     * account's entitlements last changed: every account without
     * `$changedSince`, otherwise only those changed at or after it. Each
     * item is a whole page, so the walk's `nextChangedSince` is read beside
     * its states:
     *
     *     foreach ($backend->accountStates()->list($since) as $page) {
     *         foreach ($page->states as $listed) { ... $listed->state ... }
     *         $since = $page->nextChangedSince;
     *     }
     *
     * A change made while a walk runs may be met by it or only by the next
     * one; the site advises a full walk without `$changedSince` now and then
     * (daily, say), which lists everyone.
     *
     * @param DateTimeInterface|null $changedSince the previous walk's `nextChangedSince`; sent in UTC to the second
     * @return Paginator<ListedAccountStatePage>
     */
    #[Endpoint(Operation::ListBackendEntitlements)]
    public function list(?DateTimeInterface $changedSince = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->pages(
            Operation::ListBackendEntitlements,
            ListedAccountStatePage::class,
            ['changed_since' => $changedSince],
            $limit,
            static fn(ListedAccountStatePage $page): ?string => $page->nextCursor,
        );
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
        return $this->caller->read(Operation::GetBackendEntitlement, AccountState::class, ['sub' => $subject]);
    }
}

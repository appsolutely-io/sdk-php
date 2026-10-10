<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Sync;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\Order;
use Appsolutely\Sdk\Model\SyncPull;

/**
 * The changes to the member's own orders.
 */
final readonly class MemberOrderFeed
{
    /** @internal obtain it from Client::forMember()->api()->sync() */
    public function __construct(private Caller $caller) {}

    /**
     * What changed since the cursor, or everything from the start without
     * one. A `cursor-expired` refusal (410) means records went without a
     * tombstone since: pull again from the start.
     *
     * @param string|null $cursor the nextCursor of the previous pull, unchanged
     * @return SyncPull<Order>
     */
    #[Endpoint(Operation::PullSyncMeOrder)]
    public function pull(?string $cursor = null, ?int $limit = null): SyncPull
    {
        return $this->caller->read(Operation::PullSyncMeOrder, 'OrderSyncPull', static fn(#[\SensitiveParameter] Fields $json): SyncPull => SyncPull::from($json, Order::from(...)), query: Caller::feed($cursor, $limit));
    }
}

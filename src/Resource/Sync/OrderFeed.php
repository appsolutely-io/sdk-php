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
 * The changes to every member's orders.
 */
final readonly class OrderFeed
{
    /** @internal obtain it from Client::api()->sync() */
    public function __construct(private Caller $caller) {}

    /**
     * What changed since the cursor, or everything from the start without
     * one. A `cursor-expired` refusal (410) means records went without a
     * tombstone since: pull again from the start.
     *
     * @param string|null $cursor the nextCursor of the previous pull, unchanged
     * @return SyncPull<Order>
     */
    #[Endpoint(Operation::PullSyncOrder)]
    public function pull(?string $cursor = null, ?int $limit = null): SyncPull
    {
        return $this->caller->read(Operation::PullSyncOrder, 'OrderSyncPull', static fn(Fields $json): SyncPull => SyncPull::from($json, Order::from(...)), query: Caller::feed($cursor, $limit));
    }
}

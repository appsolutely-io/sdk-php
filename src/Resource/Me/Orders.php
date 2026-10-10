<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\Order;

/**
 * The member's own orders.
 */
final readonly class Orders
{
    /** @internal obtain it from Client::forMember()->api()->me()->orders() */
    public function __construct(private Caller $caller) {}

    /**
     * @param 'asc'|'desc'|null $order
     * @return Paginator<Order>
     */
    #[Endpoint(Operation::ListMeOrders)]
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeOrders, Order::SCHEMA, Order::from(...), ['sort' => $sort, 'order' => $order], $limit);
    }

    #[Endpoint(Operation::GetMeOrder)]
    public function get(string $id): Order
    {
        return $this->caller->read(Operation::GetMeOrder, Order::SCHEMA, Order::from(...), ['id' => $id]);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\Order;

/**
 * Every member's orders, as the administrator.
 */
final readonly class Orders
{
    /** @internal obtain it from Client::api()->orders() */
    public function __construct(private Caller $caller) {}

    /**
     * @param 'asc'|'desc'|null $order
     * @return Paginator<Order>
     */
    #[Endpoint(Operation::ListOrders)]
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListOrders, Order::SCHEMA, Order::from(...), ['sort' => $sort, 'order' => $order], $limit);
    }

    #[Endpoint(Operation::GetOrder)]
    public function get(string $id): Order
    {
        return $this->caller->read(Operation::GetOrder, Order::SCHEMA, Order::from(...), ['id' => $id]);
    }
}

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
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT, ?string $status = null): Paginator
    {
        return $this->caller->list(Operation::ListMeOrders, Order::class, ['sort' => $sort, 'order' => $order, 'status' => $status], $limit);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<Order> */
    #[Endpoint(Operation::SwitchMeOrder)]
    public function switchPaymentMethod(string $id, string $paymentMethod, ?string $fromPayment = null, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::SwitchMeOrder, Order::class, ['id' => $id], ['payment_method' => $paymentMethod, ...\Appsolutely\Sdk\Support\OptionalFields::withoutNulls(['from_payment' => $fromPayment])], $idempotencyKey);
    }

    #[Endpoint(Operation::GetMeOrder)]
    public function get(string $id): Order
    {
        return $this->caller->read(Operation::GetMeOrder, Order::class, ['id' => $id]);
    }
}

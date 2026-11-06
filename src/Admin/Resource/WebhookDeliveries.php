<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Resource;

use Appsolutely\Sdk\Admin\AdminCaller;
use Appsolutely\Sdk\Admin\AdminOperation;
use Appsolutely\Sdk\Admin\Model\WebhookDelivery;
use Appsolutely\Sdk\Admin\Versioned;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;

/**
 * What each of the site's webhook subscriptions was owed, and how each delivery went.
 */
final readonly class WebhookDeliveries
{
    /** @internal obtain it from AdminClient */
    public function __construct(private AdminCaller $caller) {}

    /**
     * Every record, a page at a time as iteration reaches it; `page()` on the
     * result reads one page, at a cursor kept from an earlier run.
     *
     * @param string|null $sort the field to sort by
     * @param 'asc'|'desc'|null $order
     * @return Paginator<WebhookDelivery>
     */
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(AdminOperation::ListWebhookDeliveries, WebhookDelivery::class, ['sort' => $sort, 'order' => $order], $limit);
    }

    /**
     * The record with the version it was read at.
     *
     * @return Versioned<WebhookDelivery>
     */
    public function get(string $id): Versioned
    {
        return $this->caller->read(AdminOperation::GetWebhookDelivery, WebhookDelivery::class, ['webhook_delivery' => $id]);
    }

    /**
     * Sends the delivery's event again, once however often the call is
     * retried; the answer is the action's report as the site gives it.
     *
     * @param string|null $idempotencyKey one key per redelivery you mean to ask for
     * @return KeyedResult<array<array-key, mixed>>
     */
    public function redeliver(string $id, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyedAction(AdminOperation::RedeliverWebhookDelivery, ['webhook_delivery' => $id], [], $idempotencyKey);
    }
}

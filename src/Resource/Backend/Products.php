<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\Product;

/**
 * The site's product catalogue, read by the site's own backend with the
 * `catalogue:read` scope.
 */
final readonly class Products
{
    /** @internal obtain it from Client::backend()->products() */
    public function __construct(private Caller $caller) {}

    /**
     * @param 'asc'|'desc'|null $order
     * @return Paginator<Product>
     */
    #[Endpoint(Operation::ListProducts)]
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListProducts, Product::class, ['sort' => $sort, 'order' => $order], $limit);
    }

    #[Endpoint(Operation::GetProduct)]
    public function get(string $id): Product
    {
        return $this->caller->read(Operation::GetProduct, Product::class, ['id' => $id]);
    }
}

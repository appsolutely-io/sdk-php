<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\ProductFeatureValue;

/**
 * What each product grants for each feature key.
 */
final readonly class FeatureValues
{
    /** @internal obtain it from Client::backend()->featureValues() */
    public function __construct(private Caller $caller) {}

    /**
     * Every value a product grants, in one page that never has a cursor.
     *
     * @param string $product the product's slug
     * @return Page<ProductFeatureValue>
     */
    #[Endpoint(Operation::ListProductFeatureValues)]
    public function list(string $product): Page
    {
        return $this->caller->onlyPage(Operation::ListProductFeatureValues, ProductFeatureValue::SCHEMA, ProductFeatureValue::from(...), path: ['product' => $product]);
    }

    /**
     * Sets what a product grants for a key.
     *
     * @param string $product the product's slug
     * @param bool|int|string|array<array-key, mixed>|null $value a boolean, an integer or a configuration value, as the key's kind says
     */
    #[Endpoint(Operation::UpdateProductFeatureValue)]
    public function set(string $product, string $key, mixed $value): ProductFeatureValue
    {
        return $this->caller->read(Operation::UpdateProductFeatureValue, ProductFeatureValue::SCHEMA, ProductFeatureValue::from(...), ['product' => $product, 'key' => $key], body: ['value' => $value]);
    }

    /**
     * @param string $product the product's slug
     */
    #[Endpoint(Operation::DeleteProductFeatureValue)]
    public function delete(string $product, string $key): void
    {
        $this->caller->send(Operation::DeleteProductFeatureValue, ['product' => $product, 'key' => $key]);
    }
}

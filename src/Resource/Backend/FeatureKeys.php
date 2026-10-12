<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\FeatureKey;

/**
 * The features the site's own live backend registers for its products to
 * grant.
 */
final readonly class FeatureKeys
{
    /** @internal obtain it from Client::backend()->featureKeys() */
    public function __construct(private Caller $caller) {}

    /**
     * Every feature key, in one page that never has a cursor.
     *
     * @return Page<FeatureKey>
     */
    #[Endpoint(Operation::ListFeatureKeys)]
    public function list(): Page
    {
        return $this->caller->onlyPage(Operation::ListFeatureKeys, FeatureKey::SCHEMA, FeatureKey::from(...));
    }

    /**
     * Registers a key; registering one again with the same kind answers it.
     *
     * @param string $key lower-case letters, digits and underscores, in dot-separated parts
     * @param 'boolean'|'quantity'|'config' $kind the type of value a product grants for it, fixed once registered
     */
    #[Endpoint(Operation::CreateFeatureKey)]
    public function create(string $key, string $kind): FeatureKey
    {
        return $this->caller->read(Operation::CreateFeatureKey, FeatureKey::SCHEMA, FeatureKey::from(...), body: ['key' => $key, 'kind' => $kind]);
    }

    /**
     * Retires a key: no product may be given it any longer, and what was
     * granted stands.
     */
    #[Endpoint(Operation::RetireFeatureKey)]
    public function retire(string $key): FeatureKey
    {
        return $this->caller->read(Operation::RetireFeatureKey, FeatureKey::SCHEMA, FeatureKey::from(...), ['key' => $key]);
    }
}

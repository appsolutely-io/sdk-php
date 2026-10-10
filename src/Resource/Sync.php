<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Resource\Sync\ArticleFeed;
use Appsolutely\Sdk\Resource\Sync\FormEntryFeed;
use Appsolutely\Sdk\Resource\Sync\OrderFeed;
use Appsolutely\Sdk\Resource\Sync\PageFeed;
use Appsolutely\Sdk\Resource\Sync\ProductFeed;

/**
 * The administrator's sync feeds, one per resource: pull what changed
 * since a cursor, and push edits made offline where the resource takes
 * them.
 */
final readonly class Sync
{
    /** @internal obtain it from Client::api()->sync() */
    public function __construct(private Caller $caller) {}

    public function articles(): ArticleFeed
    {
        return new ArticleFeed($this->caller);
    }

    public function formEntries(): FormEntryFeed
    {
        return new FormEntryFeed($this->caller);
    }

    public function orders(): OrderFeed
    {
        return new OrderFeed($this->caller);
    }

    public function pages(): PageFeed
    {
        return new PageFeed($this->caller);
    }

    public function products(): ProductFeed
    {
        return new ProductFeed($this->caller);
    }
}

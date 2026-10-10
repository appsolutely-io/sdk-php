<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Resource\Sync\MemberAddressFeed;
use Appsolutely\Sdk\Resource\Sync\MemberOrderFeed;

/**
 * The member's own sync feeds, for an app that keeps their data offline.
 */
final readonly class MemberSync
{
    /** @internal obtain it from Client::forMember()->api()->sync() */
    public function __construct(private Caller $caller) {}

    public function addresses(): MemberAddressFeed
    {
        return new MemberAddressFeed($this->caller);
    }

    public function orders(): MemberOrderFeed
    {
        return new MemberOrderFeed($this->caller);
    }
}

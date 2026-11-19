<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\Announcement;

final readonly class Announcements
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return Page<Announcement> */
    #[Endpoint(Operation::ListAnnouncements)]
    public function list(): Page
    {
        return $this->caller->onlyPage(Operation::ListAnnouncements, Announcement::class);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Resource;

use Appsolutely\Sdk\Admin\AdminCaller;
use Appsolutely\Sdk\Admin\AdminOperation;
use Appsolutely\Sdk\Admin\Model\Page;
use Appsolutely\Sdk\Admin\Versioned;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;

/**
 * The site's pages.
 */
final readonly class Pages
{
    /** @internal obtain it from AdminClient */
    public function __construct(private AdminCaller $caller) {}

    /**
     * Every record, a page at a time as iteration reaches it; `page()` on the
     * result reads one page, at a cursor kept from an earlier run.
     *
     * @param string|null $sort the field to sort by
     * @param 'asc'|'desc'|null $order
     * @return Paginator<Page>
     */
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(AdminOperation::ListPages, Page::class, ['sort' => $sort, 'order' => $order], $limit);
    }

    /**
     * The record with the version it was read at.
     *
     * @return Versioned<Page>
     */
    public function get(string $id): Versioned
    {
        return $this->caller->read(AdminOperation::GetPage, Page::class, ['page' => $id]);
    }
}

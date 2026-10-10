<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\ContentPage;

/**
 * The site's content pages, as the administrator.
 */
final readonly class Pages
{
    /** @internal obtain it from Client::api()->pages() */
    public function __construct(private Caller $caller) {}

    /**
     * @param 'asc'|'desc'|null $order
     * @return Paginator<ContentPage>
     */
    #[Endpoint(Operation::ListPages)]
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListPages, ContentPage::SCHEMA, ContentPage::from(...), ['sort' => $sort, 'order' => $order], $limit);
    }

    #[Endpoint(Operation::GetPage)]
    public function get(string $id): ContentPage
    {
        return $this->caller->read(Operation::GetPage, ContentPage::SCHEMA, ContentPage::from(...), ['id' => $id]);
    }
}

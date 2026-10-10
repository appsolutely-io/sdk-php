<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\FormEntry;

/**
 * The submissions of the site's forms, as the administrator.
 */
final readonly class FormEntries
{
    /** @internal obtain it from Client::api()->formEntries() */
    public function __construct(private Caller $caller) {}

    /**
     * @param 'asc'|'desc'|null $order
     * @return Paginator<FormEntry>
     */
    #[Endpoint(Operation::ListFormEntries)]
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListFormEntries, FormEntry::SCHEMA, FormEntry::from(...), ['sort' => $sort, 'order' => $order], $limit);
    }

    #[Endpoint(Operation::GetFormEntry)]
    public function get(string $id): FormEntry
    {
        return $this->caller->read(Operation::GetFormEntry, FormEntry::SCHEMA, FormEntry::from(...), ['id' => $id]);
    }
}

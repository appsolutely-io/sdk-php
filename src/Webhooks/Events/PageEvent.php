<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Data\Page;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `page.created`, `page.updated` and `page.deleted`: the page as the site's
 * REST API serves it; a deleted one as it was before the deletion.
 */
final readonly class PageEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::PAGE_CREATED,
        EventType::PAGE_UPDATED,
        EventType::PAGE_DELETED,
    ];

    public function __construct(
        Event $envelope,
        public Page $page,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, Fields $data): self
    {
        return new self($envelope, Page::read($data));
    }
}

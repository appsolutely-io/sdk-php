<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Article;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `article.created`, `article.updated` and `article.deleted`: the article as
 * the site's REST API serves it; a deleted one as it was before the deletion.
 */
final readonly class ArticleEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::ARTICLE_CREATED,
        EventType::ARTICLE_UPDATED,
        EventType::ARTICLE_DELETED,
    ];

    public function __construct(
        Event $envelope,
        public Article $article,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self($envelope, Article::from($data));
    }
}

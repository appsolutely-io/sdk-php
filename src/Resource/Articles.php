<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\Article;
use DateTimeInterface;

/**
 * The site's articles, as the administrator.
 *
 * @phpstan-type ArticleChanges array{title?: string, slug?: string, description?: string, keywords?: string, content?: string, cover?: string, status?: 0|1, sort?: int<0, max>, published_at?: string|DateTimeInterface, expired_at?: string|DateTimeInterface, categories?: list<string>}
 */
final readonly class Articles
{
    /** @internal obtain it from Client::api()->articles() */
    public function __construct(private Caller $caller) {}

    /**
     * Every article, a page at a time as iteration reaches it; `page()` on
     * the result reads one page, at a cursor kept from an earlier run.
     *
     * @param string|null $sort the field to sort by, such as `published_at`
     * @param 'asc'|'desc'|null $order
     * @return Paginator<Article>
     */
    #[Endpoint(Operation::ListArticles)]
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListArticles, Article::SCHEMA, Article::from(...), ['sort' => $sort, 'order' => $order], $limit);
    }

    #[Endpoint(Operation::GetArticle)]
    public function get(string $id): Article
    {
        return $this->caller->read(Operation::GetArticle, Article::SCHEMA, Article::from(...), ['id' => $id]);
    }

    /**
     * Creates an article once, however often the call is retried: it is
     * sent with an Idempotency-Key, the caller's or a fresh one, and a
     * retry is answered with the first answer.
     *
     * @param array{title: string, content: string, slug?: string, description?: string, keywords?: string, cover?: string, status?: 0|1, sort?: int<0, max>, published_at?: string|DateTimeInterface, expired_at?: string|DateTimeInterface, categories?: list<string>} $article
     *        times are sent in UTC to the second; categories are category ids
     * @param string|null $idempotencyKey one key per article you mean to create; reuse it only to retry the same request
     * @return KeyedResult<Article>
     */
    #[Endpoint(Operation::CreateArticle)]
    public function create(array $article, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateArticle, Article::SCHEMA, Article::from(...), [], $article, $idempotencyKey);
    }

    /**
     * Changes the fields given and leaves the rest.
     *
     * @param ArticleChanges $changes
     */
    #[Endpoint(Operation::UpdateArticle)]
    public function update(string $id, array $changes): Article
    {
        return $this->caller->read(Operation::UpdateArticle, Article::SCHEMA, Article::from(...), ['id' => $id], body: $changes);
    }
}

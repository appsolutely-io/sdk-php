<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Resource;

use Appsolutely\Sdk\Admin\AdminCaller;
use Appsolutely\Sdk\Admin\AdminOperation;
use Appsolutely\Sdk\Admin\Model\Article;
use Appsolutely\Sdk\Admin\Versioned;
use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;

/**
 * The site's articles.
 */
final readonly class Articles
{
    /** @internal obtain it from AdminClient */
    public function __construct(private AdminCaller $caller) {}

    /**
     * Every record, a page at a time as iteration reaches it; `page()` on the
     * result reads one page, at a cursor kept from an earlier run.
     *
     * @param string|null $sort the field to sort by
     * @param 'asc'|'desc'|null $order
     * @return Paginator<Article>
     */
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(AdminOperation::ListArticles, Article::class, ['sort' => $sort, 'order' => $order], $limit);
    }

    /**
     * The record with the version it was read at.
     *
     * @return Versioned<Article>
     */
    public function get(string $id): Versioned
    {
        return $this->caller->read(AdminOperation::GetArticle, Article::class, ['article' => $id]);
    }

    /**
     * Creates an article once, however often the call is retried: it is sent
     * with an Idempotency-Key, the caller's or a fresh one.
     *
     * @param array<string, mixed> $article the fields the Administrator API document lists for a new article, such as `title`, `content` and `status`
     * @param string|null $idempotencyKey one key per article you mean to create; reuse it only to retry the same request
     * @return KeyedResult<Article>
     */
    public function create(array $article, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(AdminOperation::CreateArticle, Article::class, [], $article, $idempotencyKey);
    }

    /**
     * Changes the fields given over the version read, named by its ETag; an
     * article changed since is refused as a 412 rather than overwritten.
     *
     * @param array<string, mixed> $changes
     * @return Versioned<Article>
     */
    public function update(string $id, string $etag, array $changes): Versioned
    {
        return $this->caller->update(AdminOperation::UpdateArticle, Article::class, ['article' => $id], $etag, $changes);
    }

    /**
     * Deletes the version read, named by its ETag.
     */
    public function delete(string $id, string $etag): ApiResponse
    {
        return $this->caller->delete(AdminOperation::DeleteArticle, ['article' => $id], $etag);
    }
}

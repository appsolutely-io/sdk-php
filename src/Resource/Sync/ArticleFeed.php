<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Sync;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Article;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\SyncPull;
use Appsolutely\Sdk\Model\SyncPush;
use DateTimeInterface;

/**
 * The changes to the site's articles, and edits to them made offline.
 */
final readonly class ArticleFeed
{
    /** @internal obtain it from Client::api()->sync() */
    public function __construct(private Caller $caller) {}

    /**
     * What changed since the cursor, or everything from the start without
     * one. A `cursor-expired` refusal (410) means records went without a
     * tombstone since: pull again from the start.
     *
     * @param string|null $cursor the nextCursor of the previous pull, unchanged
     * @return SyncPull<Article>
     */
    #[Endpoint(Operation::PullSyncArticle)]
    public function pull(?string $cursor = null, ?int $limit = null): SyncPull
    {
        return $this->caller->read(Operation::PullSyncArticle, 'ArticleSyncPull', static fn(#[\SensitiveParameter] Fields $json): SyncPull => SyncPull::from($json, Article::from(...)), query: Caller::feed($cursor, $limit));
    }

    /**
     * Applies a batch of edits made offline, each answered on its own. A
     * mutation sent again under the same mutation id is answered from the
     * site's record of it rather than applied twice, so the batch is safe
     * to resend after a lost answer.
     *
     * @param non-empty-list<array{mutation_id: string, op: 'upsert'|'delete', id?: string|null, client_updated_at: string|DateTimeInterface, attributes?: array<string, mixed>}> $mutations
     *        mutation_id: letters, digits, `_` and `-`, made up by the client; id: required to delete
     * @return SyncPush<Article>
     */
    #[Endpoint(Operation::PushSyncArticle)]
    public function push(array $mutations): SyncPush
    {
        return $this->caller->read(Operation::PushSyncArticle, 'ArticleSyncPush', static fn(#[\SensitiveParameter] Fields $json): SyncPush => SyncPush::from($json, Article::from(...)), body: ['mutations' => $mutations]);
    }
}

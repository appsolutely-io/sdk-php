<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Sync;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Address;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\SyncPull;
use Appsolutely\Sdk\Model\SyncPush;
use DateTimeInterface;

/**
 * The changes to the member's own addresses, and edits to them made offline.
 */
final readonly class MemberAddressFeed
{
    /** @internal obtain it from Client::forMember()->api()->sync() */
    public function __construct(private Caller $caller) {}

    /**
     * What changed since the cursor, or everything from the start without
     * one. A `cursor-expired` refusal (410) means records went without a
     * tombstone since: pull again from the start.
     *
     * @param string|null $cursor the nextCursor of the previous pull, unchanged
     * @return SyncPull<Address>
     */
    #[Endpoint(Operation::PullSyncMeAddress)]
    public function pull(?string $cursor = null, ?int $limit = null): SyncPull
    {
        return $this->caller->read(Operation::PullSyncMeAddress, 'UserAddressSyncPull', static fn(#[\SensitiveParameter] Fields $json): SyncPull => SyncPull::from($json, Address::from(...)), query: Caller::feed($cursor, $limit));
    }

    /**
     * Applies a batch of edits made offline, each answered on its own. A
     * mutation sent again under the same mutation id is answered from the
     * site's record of it rather than applied twice, so the batch is safe
     * to resend after a lost answer.
     *
     * @param non-empty-list<array{mutation_id: string, op: 'upsert'|'delete', id?: string|null, client_updated_at: string|DateTimeInterface, attributes?: array<string, mixed>}> $mutations
     *        mutation_id: letters, digits, `_` and `-`, made up by the client; id: required to delete
     * @return SyncPush<Address>
     */
    #[Endpoint(Operation::PushSyncMeAddress)]
    public function push(array $mutations): SyncPush
    {
        return $this->caller->read(Operation::PushSyncMeAddress, 'UserAddressSyncPush', static fn(#[\SensitiveParameter] Fields $json): SyncPush => SyncPush::from($json, Address::from(...)), body: ['mutations' => $mutations]);
    }
}

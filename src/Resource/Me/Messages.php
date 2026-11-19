<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\ConditionalResult;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\InboxChangeFeed;
use Appsolutely\Sdk\Model\InboxMessagePage;
use Appsolutely\Sdk\Model\InboxUnreadCount;

final readonly class Messages
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return Paginator<InboxMessagePage> */
    #[Endpoint(Operation::ListMeMessages)]
    public function list(int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->pages(Operation::ListMeMessages, InboxMessagePage::class, [], $limit, static fn(InboxMessagePage $page): ?string => $page->nextCursor);
    }

    /** @return ConditionalResult<InboxMessagePage> */
    public function listConditional(string $ifNoneMatch, int $limit = SiteApi::DEFAULT_LIMIT, ?string $cursor = null): ConditionalResult
    {
        SiteApi::assertPaging([], $limit);

        return $this->caller->conditionalRead(Operation::ListMeMessages, InboxMessagePage::class, $ifNoneMatch, ['limit' => $limit, 'cursor' => $cursor]);
    }

    #[Endpoint(Operation::ListMeMessageChanges)]
    public function changes(string $cursor, int $limit = SiteApi::DEFAULT_LIMIT): InboxChangeFeed
    {
        SiteApi::assertPaging([], $limit);

        return $this->caller->read(Operation::ListMeMessageChanges, InboxChangeFeed::class, query: ['cursor' => $cursor, 'limit' => $limit]);
    }

    #[Endpoint(Operation::ReadMeInbox)]
    public function readAll(string $throughSequence): ApiResponse
    {
        return $this->caller->send(Operation::ReadMeInbox, body: ['through_sequence' => $throughSequence]);
    }

    #[Endpoint(Operation::GetMeMessageUnreadCount)]
    public function unreadCount(): InboxUnreadCount
    {
        return $this->caller->read(Operation::GetMeMessageUnreadCount, InboxUnreadCount::class);
    }

    #[Endpoint(Operation::DeleteMeMessage)]
    public function delete(string $reference): ApiResponse
    {
        return $this->caller->send(Operation::DeleteMeMessage, ['reference' => $reference]);
    }

    #[Endpoint(Operation::ReadMeMessage)]
    public function read(string $reference): ApiResponse
    {
        return $this->caller->send(Operation::ReadMeMessage, ['reference' => $reference]);
    }

    #[Endpoint(Operation::UnreadMeMessage)]
    public function unread(string $reference): ApiResponse
    {
        return $this->caller->send(Operation::UnreadMeMessage, ['reference' => $reference]);
    }
}

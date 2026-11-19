<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\ValidationFailedException;
use Appsolutely\Sdk\Http\Header;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagingTest extends TestCase
{
    private static function api(SiteRecorder $site): MemberApi
    {
        return $site->provider->client()->forMember('member-access-token')->api();
    }

    public function testInboxPagesKeepTheSequenceAndOpaqueCursor(): void
    {
        $sequence = '18446744073709551615';
        $site = (new SiteRecorder())->json(['data' => [], 'sequence' => $sequence, 'next_cursor' => 'opaque/next+page'])->json(['data' => [], 'sequence' => $sequence]);

        $pages = iterator_to_array(self::api($site)->me()->messages()->list(limit: 2), false);

        self::assertCount(2, $pages);
        self::assertSame($sequence, $pages[0]->sequence);
        self::assertSame('opaque/next+page', $pages[0]->nextCursor);
        self::assertSame('GET /api/v1/me/messages?limit=2&cursor=opaque%2Fnext%2Bpage', $site->line(1));
        self::assertSame('Bearer member-access-token', $site->request()->getHeaderLine(Header::AUTHORIZATION));
    }

    public function testTheChangesFeedAndReadAllUseStringBoundaries(): void
    {
        $site = (new SiteRecorder())->json(['changes' => [['sequence' => '18446744073709551615', 'kind' => 'read-all', 'reference' => null, 'through_sequence' => '18446744073709551614']], 'next_cursor' => 'opaque/next', 'has_more' => true])->empty(204);
        $messages = self::api($site)->me()->messages();

        $feed = $messages->changes('opaque/start');
        $answer = $messages->readAll('18446744073709551614');

        self::assertSame('GET /api/v1/me/messages/changes?cursor=opaque%2Fstart&limit=25', $site->line());
        self::assertSame('18446744073709551615', $feed->changes[0]->sequence);
        self::assertNull($feed->changes[0]->reference);
        self::assertSame('18446744073709551614', $feed->changes[0]->throughSequence);
        self::assertSame(['through_sequence' => '18446744073709551614'], $site->body(1));
        self::assertSame(204, $answer->status);
        self::assertNull($answer->data);
    }

    public function testAnExpiredChangesCursorRemainsA410(): void
    {
        $site = (new SiteRecorder())->problem(410, 'cursor-expired');
        try {
            self::api($site)->me()->messages()->changes('expired');
        } catch (ApiException $failure) {
            self::assertSame(410, $failure->status);
            return;
        }
        self::fail('The expired cursor was read as an empty feed.');
    }

    public function testMessageActionsEncodeOneReferenceAndAcceptNoBody(): void
    {
        $site = (new SiteRecorder())->empty(204)->empty(204)->empty(204)->empty(204);
        $me = self::api($site)->me();
        $me->messages()->read('message/one');
        $me->messages()->unread('message/one');
        $me->messages()->delete('message/one');
        $me->announcements()->dismiss('announcement/one');

        self::assertSame('POST /api/v1/me/messages/message%2Fone/read', $site->line());
        self::assertSame('POST /api/v1/me/messages/message%2Fone/unread', $site->line(1));
        self::assertSame('DELETE /api/v1/me/messages/message%2Fone', $site->line(2));
        self::assertSame('POST /api/v1/me/announcements/announcement%2Fone/dismiss', $site->line(3));
    }

    public function testPublicAnnouncementsDoNotSendTheMembersCredential(): void
    {
        $site = (new SiteRecorder())->json(['data' => []]);
        self::assertSame([], $site->provider->client()->api()->announcements()->list()->items);
        self::assertSame('', $site->request()->getHeaderLine(Header::AUTHORIZATION));
        self::assertSame('GET /api/v1/announcements', $site->line());
    }

    public function testPreferenceUpdatesContainOnlyTheSuppliedChoices(): void
    {
        $site = (new SiteRecorder())->json(['data' => []]);
        $preferences = [['category' => 'product_updates', 'channel' => 'push', 'enabled' => false]];
        self::api($site)->me()->messagePreferences()->update($preferences);
        self::assertSame('PUT /api/v1/me/message-preferences', $site->line());
        self::assertSame(['preferences' => $preferences], $site->body());
    }

    public function testARefusedPreferenceIsNotAnEmptySuccessfulUpdate(): void
    {
        $site = (new SiteRecorder())->problem(422, 'validation-failed');
        $this->expectException(ValidationFailedException::class);
        self::api($site)->me()->messagePreferences()->update([['category' => 'security', 'channel' => 'push', 'enabled' => false]]);
    }

    /** @return iterable<string, array{int}> */
    public static function deletionStatuses(): iterable
    {
        yield 'first acceptance' => [202];
        yield 'already accepted' => [200];
    }

    #[DataProvider('deletionStatuses')]
    public function testDeletionAcceptanceKeepsTheCallersKeyAndStatus(int $status): void
    {
        $site = (new SiteRecorder())->json(['state' => 'pending', 'due_at' => '2026-12-01T00:00:00Z', 'cancel_url' => 'https://site.example/deletion/cancel'], $status);
        $answer = self::api($site)->me()->deletion()->create('delete-account-once');
        self::assertSame($status, $answer->response->status);
        self::assertSame('delete-account-once', $answer->idempotencyKey);
        self::assertSame('"delete-account-once"', $site->request()->getHeaderLine(Header::IDEMPOTENCY_KEY));
        self::assertSame('pending', $answer->value->state);
    }

    public function testDisabledRealtimeIsANotFoundRefusal(): void
    {
        $site = (new SiteRecorder())->problem(404, 'not-found');
        $this->expectException(NotFoundException::class);
        self::api($site)->me()->realtime()->get();
    }

    public function testRealtimeEventsRequireAndSendTheAfterBoundary(): void
    {
        $site = (new SiteRecorder())->json(['hints' => [], 'next' => 42, 'resync' => true]);
        $answer = self::api($site)->me()->realtime()->events(0);
        self::assertSame('GET /api/v1/me/realtime/events?after=0', $site->line());
        self::assertSame(42, $answer->next);
        self::assertTrue($answer->resync);
    }

    public function testBackendMessagesPreserveOptionalDataAndTheCallerKey(): void
    {
        $site = (new SiteRecorder())->json(['id' => 'message-one', 'state' => 'queued'], 201);
        $site->provider->token = static fn() => $site->provider->json(['access_token' => 'machine-token', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        $answer = $site->provider->backendClient(['messages:write'])->backend()->memberMessages()->create('member/one', 'service', 'Ready', 'Your report is ready.', data: ['report' => 'one'], channels: ['in_app'], idempotencyKey: 'report-ready-once');

        self::assertSame('POST /api/v1/members/member%2Fone/messages', $site->line());
        self::assertSame(['category' => 'service', 'title' => 'Ready', 'body' => 'Your report is ready.', 'data' => ['report' => 'one'], 'channels' => ['in_app']], $site->body());
        self::assertSame('report-ready-once', $answer->idempotencyKey);
        self::assertSame('queued', $answer->value->state);
        self::assertNotSame('Bearer member-access-token', $site->request()->getHeaderLine(Header::AUTHORIZATION));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\ValidationFailedException;
use Appsolutely\Sdk\Model\Address;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The member's sync feeds: a pull returns what changed since a cursor, as
 * records and tombstones, and a push applies a batch of edits made offline,
 * each answered on its own.
 */
final class SyncTest extends TestCase
{
    public function testAPullReadsTheChangesSinceACursor(): void
    {
        $site = (new SiteRecorder())->json([
            'upserts' => [MemberTest::address('addr-1')],
            'tombstones' => [['id' => 'addr-0', 'deleted_at' => '2026-10-08T10:00:00Z']],
            'next_cursor' => 'w-2',
            'has_more' => false,
        ]);

        $pull = self::api($site)->sync()->addresses()->pull(cursor: 'w-1', limit: 200);

        self::assertSame('GET /api/v1/sync/me-addresses?cursor=w-1&limit=200', $site->line());
        self::assertInstanceOf(Address::class, $pull->upserts[0]);
        self::assertSame('addr-1', $pull->upserts[0]->id);
        self::assertSame('addr-0', $pull->tombstones[0]->id);
        self::assertSame('2026-10-08T10:00:00+00:00', $pull->tombstones[0]->deletedAt->format(DATE_ATOM));
        self::assertSame('w-2', $pull->nextCursor);
        self::assertFalse($pull->hasMore);
    }

    public function testTheFirstPullSendsNeitherCursorNorLimit(): void
    {
        $empty = ['upserts' => [], 'tombstones' => [], 'next_cursor' => 'w-1', 'has_more' => false];
        $site = (new SiteRecorder())->json($empty)->json($empty);
        $sync = self::api($site)->sync();

        $sync->addresses()->pull();
        $sync->orders()->pull();

        self::assertSame('GET /api/v1/sync/me-addresses', $site->line(0));
        self::assertSame('GET /api/v1/sync/me-orders', $site->line(1));
    }

    public function testEachFeedReadsItsOwnRecords(): void
    {
        $site = (new SiteRecorder())
            ->json(['upserts' => [MemberTest::address()], 'tombstones' => [], 'next_cursor' => 'a', 'has_more' => true])
            ->json(['upserts' => [OrdersTest::order()], 'tombstones' => [], 'next_cursor' => 'b', 'has_more' => false]);
        $sync = self::api($site)->sync();

        self::assertSame('London', $sync->addresses()->pull()->upserts[0]->city);
        self::assertSame(1900, $sync->orders()->pull()->upserts[0]->totalAmount);
    }

    public function testAPushSendsTheBatchAndReadsEachMutationsResult(): void
    {
        $site = (new SiteRecorder())->json(['results' => [
            ['mutation_id' => 'm-1', 'id' => 'addr-1', 'applied' => true, 'reason' => null, 'errors' => null, 'server_record' => MemberTest::address('addr-1')],
            ['mutation_id' => 'm-2', 'id' => null, 'applied' => false, 'reason' => 'validation_failed', 'errors' => ['city' => ['Required.']], 'server_record' => null],
        ]]);

        $push = self::api($site)->sync()->addresses()->push([
            ['mutation_id' => 'm-1', 'op' => 'upsert', 'id' => 'addr-1', 'client_updated_at' => new DateTimeImmutable('2026-10-08T14:00:00+02:00'), 'attributes' => ['city' => 'Leeds']],
            ['mutation_id' => 'm-2', 'op' => 'upsert', 'client_updated_at' => '2026-10-08T12:00:00Z', 'attributes' => []],
        ]);

        self::assertSame('POST /api/v1/sync/me-addresses', $site->line());
        self::assertFalse($site->request()->hasHeader('Idempotency-Key'));
        self::assertSame([
            'mutations' => [
                ['mutation_id' => 'm-1', 'op' => 'upsert', 'id' => 'addr-1', 'client_updated_at' => '2026-10-08T12:00:00Z', 'attributes' => ['city' => 'Leeds']],
                ['mutation_id' => 'm-2', 'op' => 'upsert', 'client_updated_at' => '2026-10-08T12:00:00Z', 'attributes' => []],
            ],
        ], $site->body());
        self::assertTrue($push->results[0]->applied);
        self::assertSame('addr-1', $push->results[0]->serverRecord?->id);
        self::assertFalse($push->results[1]->applied);
        self::assertSame('validation_failed', $push->results[1]->reason);
        self::assertSame(['city' => ['Required.']], $push->results[1]->errors);
        self::assertNull($push->results[1]->serverRecord);
    }

    public function testAnExpiredCursorIsTheSitesRefusal(): void
    {
        $site = (new SiteRecorder())->problem(410, 'cursor-expired');

        try {
            self::api($site)->sync()->orders()->pull('w-old');
            self::fail('The refusal was not thrown.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->hasType('cursor-expired'));
        }
    }

    /**
     * The site bounds a pull itself, 500 unless it is configured otherwise,
     * so a larger limit is sent as given and the site's refusal is thrown:
     * it is no longer cut short, which read like the end of the changes.
     */
    public function testALimitAboveTheSitesMaximumIsTheSitesRefusal(): void
    {
        $site = (new SiteRecorder())->problem(422, 'validation-failed', ['errors' => ['limit' => ['The limit may not be greater than 500.']]]);

        try {
            self::api($site)->sync()->orders()->pull(limit: 501);
            self::fail('The refusal was not thrown.');
        } catch (ValidationFailedException $exception) {
            self::assertSame(['limit' => ['The limit may not be greater than 500.']], $exception->errors);
        }
        self::assertSame('GET /api/v1/sync/me-orders?limit=501', $site->line());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }

    #[DataProvider('refusedLimits')]
    public function testALimitBelowOneIsRefusedBeforeAnyRequest(int $limit): void
    {
        $site = new SiteRecorder();

        try {
            self::api($site)->sync()->addresses()->pull(limit: $limit);
            self::fail('The limit was accepted.');
        } catch (InvalidArgumentValueException) {
        }
        self::assertSame([], $site->provider->siteRequests());
    }

    private static function api(SiteRecorder $site): MemberApi
    {
        return $site->provider->client()->forMember('member-access-token')->api();
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Model\Article;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The administrator's sync feeds: a pull returns what changed since a
 * cursor, as records and tombstones, and a push applies a batch of edits
 * made offline, each answered on its own.
 */
final class SyncTest extends TestCase
{
    public function testAPullReadsTheChangesSinceACursor(): void
    {
        $site = (new SiteRecorder())->json([
            'upserts' => [ContentTest::article('art-1')],
            'tombstones' => [['id' => 'art-0', 'deleted_at' => '2026-10-08T10:00:00Z']],
            'next_cursor' => 'w-2',
            'has_more' => false,
        ]);

        $pull = $site->provider->client()->api()->sync()->articles()->pull(cursor: 'w-1', limit: 200);

        self::assertSame('GET /api/v1/sync/articles?cursor=w-1&limit=200', $site->line());
        self::assertInstanceOf(Article::class, $pull->upserts[0]);
        self::assertSame('art-1', $pull->upserts[0]->id);
        self::assertSame('art-0', $pull->tombstones[0]->id);
        self::assertSame('2026-10-08T10:00:00+00:00', $pull->tombstones[0]->deletedAt->format(DATE_ATOM));
        self::assertSame('w-2', $pull->nextCursor);
        self::assertFalse($pull->hasMore);
    }

    public function testTheFirstPullSendsNeitherCursorNorLimit(): void
    {
        $empty = ['upserts' => [], 'tombstones' => [], 'next_cursor' => 'w-1', 'has_more' => false];
        $site = (new SiteRecorder())->json($empty)->json($empty)->json($empty)->json($empty)->json($empty);
        $sync = $site->provider->client()->api()->sync();

        $sync->articles()->pull();
        $sync->formEntries()->pull();
        $sync->orders()->pull();
        $sync->pages()->pull();
        $sync->products()->pull();

        self::assertSame('GET /api/v1/sync/articles', $site->line(0));
        self::assertSame('GET /api/v1/sync/form-entries', $site->line(1));
        self::assertSame('GET /api/v1/sync/orders', $site->line(2));
        self::assertSame('GET /api/v1/sync/pages', $site->line(3));
        self::assertSame('GET /api/v1/sync/products', $site->line(4));
    }

    public function testEachFeedReadsItsOwnRecords(): void
    {
        $site = (new SiteRecorder())
            ->json(['upserts' => [RecordsTest::formEntry()], 'tombstones' => [], 'next_cursor' => 'a', 'has_more' => true])
            ->json(['upserts' => [RecordsTest::order()], 'tombstones' => [], 'next_cursor' => 'b', 'has_more' => false])
            ->json(['upserts' => [ContentTest::page()], 'tombstones' => [], 'next_cursor' => 'c', 'has_more' => false])
            ->json(['upserts' => [ContentTest::product()], 'tombstones' => [], 'next_cursor' => 'd', 'has_more' => false]);
        $sync = $site->provider->client()->api()->sync();

        self::assertSame('fe-1', $sync->formEntries()->pull()->upserts[0]->id);
        self::assertSame(2659, $sync->orders()->pull()->upserts[0]->totalAmount);
        self::assertSame('About', $sync->pages()->pull()->upserts[0]->name);
        self::assertSame('subscription', $sync->products()->pull()->upserts[0]->type);
    }

    public function testAPushSendsTheBatchAndReadsEachMutationsResult(): void
    {
        $site = (new SiteRecorder())->json(['results' => [
            ['mutation_id' => 'm-1', 'id' => 'art-1', 'applied' => true, 'reason' => null, 'errors' => null, 'server_record' => ContentTest::article('art-1')],
            ['mutation_id' => 'm-2', 'id' => null, 'applied' => false, 'reason' => 'validation_failed', 'errors' => ['title' => ['Required.']], 'server_record' => null],
        ]]);

        $push = $site->provider->client()->api()->sync()->articles()->push([
            ['mutation_id' => 'm-1', 'op' => 'upsert', 'id' => 'art-1', 'client_updated_at' => new DateTimeImmutable('2026-10-08T14:00:00+02:00'), 'attributes' => ['title' => 'Hi']],
            ['mutation_id' => 'm-2', 'op' => 'upsert', 'client_updated_at' => '2026-10-08T12:00:00Z', 'attributes' => []],
        ]);

        self::assertSame('POST /api/v1/sync/articles', $site->line());
        self::assertFalse($site->request()->hasHeader('Idempotency-Key'));
        self::assertSame([
            'mutations' => [
                ['mutation_id' => 'm-1', 'op' => 'upsert', 'id' => 'art-1', 'client_updated_at' => '2026-10-08T12:00:00Z', 'attributes' => ['title' => 'Hi']],
                ['mutation_id' => 'm-2', 'op' => 'upsert', 'client_updated_at' => '2026-10-08T12:00:00Z', 'attributes' => []],
            ],
        ], $site->body());
        self::assertTrue($push->results[0]->applied);
        self::assertSame('art-1', $push->results[0]->serverRecord?->id);
        self::assertFalse($push->results[1]->applied);
        self::assertSame('validation_failed', $push->results[1]->reason);
        self::assertSame(['title' => ['Required.']], $push->results[1]->errors);
        self::assertNull($push->results[1]->serverRecord);
    }

    public function testAnExpiredCursorIsTheSitesRefusal(): void
    {
        $site = (new SiteRecorder())->problem(410, 'cursor-expired');

        try {
            $site->provider->client()->api()->sync()->orders()->pull('w-old');
            self::fail('The refusal was not thrown.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->hasType('cursor-expired'));
        }
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
            $site->provider->client()->api()->sync()->pages()->pull(limit: $limit);
            self::fail('The limit was accepted.');
        } catch (InvalidArgumentValueException) {
        }
        self::assertSame([], $site->provider->siteRequests());
    }
}

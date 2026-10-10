<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A list is a `{data, next_cursor}` page; the next page is asked for with
 * the previous page's cursor, unchanged, until a page carries none.
 */
final class PagingTest extends TestCase
{
    public function testAPageHoldsItsItemsAndTheCursorToTheNext(): void
    {
        $provider = self::pages([[['id' => 'a'], ['id' => 'b']], 'cursor-2']);

        $page = $provider->client()->api()->raw()->page('/api/v1/articles', ['status' => 'published'], limit: 2);

        self::assertSame([['id' => 'a'], ['id' => 'b']], $page->items);
        self::assertSame('cursor-2', $page->nextCursor);
        self::assertTrue($page->hasMore());
        self::assertSame('status=published&limit=2', $provider->siteRequests()[0]->getUri()->getQuery());
    }

    public function testTheLastPageHasNoCursor(): void
    {
        $provider = self::pages([[['id' => 'a']], null]);

        $page = $provider->client()->api()->raw()->page('/api/v1/articles');

        self::assertNull($page->nextCursor);
        self::assertFalse($page->hasMore());
        self::assertSame('limit=25', $provider->siteRequests()[0]->getUri()->getQuery());
    }

    public function testIteratingFollowsTheCursorUnchangedAndKeepsTheFilters(): void
    {
        $provider = self::pages([[['id' => 'a'], ['id' => 'b']], 'c/2+=='], [[['id' => 'c']], 'c3'], [[], null]);

        $ids = [];
        foreach ($provider->client()->api()->raw()->paginate('/api/v1/articles', ['status' => 'published'], limit: 2) as $article) {
            $ids[] = $article['id'];
        }

        self::assertSame(['a', 'b', 'c'], $ids);
        $queries = array_map(static fn(RequestInterface $request): string => urldecode($request->getUri()->getQuery()), $provider->siteRequests());
        self::assertSame(['status=published&limit=2', 'status=published&limit=2&cursor=c/2+==', 'status=published&limit=2&cursor=c3'], $queries);
    }

    public function testThePaginatorAsksForANextPageOnlyWhenItIsReached(): void
    {
        $provider = self::pages([[['id' => 'a'], ['id' => 'b']], 'c2'], [[['id' => 'c']], null]);

        $paginator = $provider->client()->api()->raw()->paginate('/api/v1/articles');
        self::assertSame([], $provider->siteRequests());

        foreach ($paginator as $article) {
            self::assertSame('a', $article['id']);
            break;
        }
        self::assertCount(1, $provider->siteRequests());
    }

    public function testPagesCanBeWalkedOneByOne(): void
    {
        $provider = self::pages([[['id' => 'a']], 'c2'], [[['id' => 'b']], null]);

        $cursors = [];
        foreach ($provider->client()->api()->raw()->paginate('/api/v1/articles')->pages() as $page) {
            $cursors[] = $page->nextCursor;
        }

        self::assertSame(['c2', null], $cursors);
    }

    public function testOnePageIsFetchedAtACursorKeptFromAnEarlierRun(): void
    {
        $provider = self::pages([[['id' => 'b']], null]);

        $page = $provider->client()->api()->raw()->paginate('/api/v1/articles', limit: 2)->page('kept-cursor');

        self::assertSame([['id' => 'b']], $page->items);
        self::assertCount(1, $provider->siteRequests());
        self::assertSame('limit=2&cursor=kept-cursor', $provider->siteRequests()[0]->getUri()->getQuery());
    }

    public function testItemsCanBeMappedLazily(): void
    {
        $provider = self::pages([[['id' => 'a']], 'c2'], [[['id' => 'b']], null]);

        $ids = iterator_to_array($provider->client()->api()->raw()->paginate('/api/v1/articles')->map(static fn(array $item): mixed => $item['id']), false);

        self::assertSame(['a', 'b'], $ids);
    }

    public function testAMemberListsWithTheMembersToken(): void
    {
        $provider = self::pages([[], null]);

        iterator_to_array($provider->client()->forMember('member-token')->api()->raw()->paginate('/api/v1/me/orders'));

        self::assertSame('Bearer member-token', $provider->siteRequests()[0]->getHeaderLine('Authorization'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'beyond a hundred' => [101];
    }

    #[DataProvider('refusedLimits')]
    public function testALimitOutsideOneToAHundredIsRefusedBeforeAnyRequest(int $limit): void
    {
        $provider = self::pages([[], null]);
        $api = $provider->client()->api()->raw();

        foreach ([static fn() => $api->paginate('/api/v1/articles', limit: $limit), static fn() => $api->page('/api/v1/articles', limit: $limit)] as $call) {
            try {
                $call();
                self::fail('The limit was accepted.');
            } catch (InvalidArgumentValueException) {
            }
        }
        self::assertSame([], $provider->siteRequests());
    }

    /**
     * The cursor is the site's; one built by hand is refused by the site, so
     * passing `limit` or `cursor` among the filters is refused here.
     *
     * @return iterable<string, array{string}>
     */
    public static function reservedParameters(): iterable
    {
        yield 'cursor' => ['cursor'];
        yield 'limit' => ['limit'];
    }

    #[DataProvider('reservedParameters')]
    public function testThePagingParametersCannotBePassedAsFilters(string $name): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        (new FakeProvider())->client()->api()->raw()->paginate('/api/v1/articles', [$name => 'x']);
    }

    public function testTheLimitsAtTheEdgesAreAccepted(): void
    {
        $provider = self::pages([[], null], [[], null]);

        $provider->client()->api()->raw()->page('/api/v1/articles', limit: 1);
        $provider->client()->api()->raw()->page('/api/v1/articles', limit: 100);

        self::assertCount(2, $provider->siteRequests());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function notPages(): iterable
    {
        yield 'no data' => [['items' => []]];
        yield 'data that is not a list' => [['data' => ['id' => 'a']]];
        yield 'an item that is not an object' => [['data' => ['a']]];
        yield 'a cursor that is not a string' => [['data' => [], 'next_cursor' => 5]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('notPages')]
    public function testAnAnswerThatIsNotAPageIsRefused(array $body): void
    {
        $provider = new FakeProvider();
        $provider->site = static fn(): ResponseInterface => $provider->json($body);

        $this->expectException(UnexpectedResponseException::class);

        $provider->client()->api()->raw()->page('/api/v1/articles');
    }

    public function testASiteThatHandsBackTheSameCursorDoesNotLoopForeverYetKeepsThePagesItems(): void
    {
        $provider = self::pages([[['id' => 'a']], 'same'], [[['id' => 'b']], 'same']);

        $ids = [];
        try {
            foreach ($provider->client()->api()->raw()->paginate('/api/v1/articles') as $item) {
                $ids[] = $item['id'] ?? null;
            }
            self::fail('The repeated cursor was followed.');
        } catch (UnexpectedResponseException) {
        }

        self::assertSame(['a', 'b'], $ids);
        self::assertCount(2, $provider->siteRequests());
    }

    public function testASiteWhoseCursorsLeadBackToAnEarlierPageDoesNotLoopForever(): void
    {
        $next = ['' => 'A', 'A' => 'B', 'B' => 'A'];
        $provider = new FakeProvider();
        $provider->site = static function (RequestInterface $request) use ($provider, $next): ResponseInterface {
            parse_str($request->getUri()->getQuery(), $query);
            $cursor = is_string($query['cursor'] ?? null) ? $query['cursor'] : '';

            return $provider->json(['data' => [['id' => $cursor]], 'next_cursor' => $next[$cursor]]);
        };

        $ids = [];
        try {
            foreach ($provider->client()->api()->raw()->paginate('/api/v1/articles') as $item) {
                $ids[] = $item['id'] ?? null;
            }
            self::fail('The cycle was followed to its end.');
        } catch (UnexpectedResponseException) {
        }

        self::assertSame(['', 'A', 'B'], $ids);
        self::assertCount(3, $provider->siteRequests());
    }

    public function testARefusedCursorIsThrown(): void
    {
        $provider = new FakeProvider();
        $provider->site = static fn(): ResponseInterface => $provider->problem(422, 'invalid-cursor');

        try {
            $provider->client()->api()->raw()->page('/api/v1/articles', cursor: 'stale');
            self::fail('The refusal was not thrown.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->hasType('invalid-cursor'));
        }
    }

    /**
     * A site answering with the given pages in turn.
     *
     * @param array{list<mixed>, string|null} ...$pages
     */
    private static function pages(array ...$pages): FakeProvider
    {
        $provider = new FakeProvider();
        $provider->site = static function () use ($provider, &$pages): ResponseInterface {
            [$items, $cursor] = array_shift($pages) ?? [[], null];

            return $provider->json(['data' => $items, ...($cursor === null ? [] : ['next_cursor' => $cursor])]);
        };

        return $provider;
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\ValidationFailedException;
use Appsolutely\Sdk\Model\Article;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\SiteFixture;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Pages, articles and products, read and written by the administrator.
 */
final class ContentTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    public static function article(string $id = 'art-1'): array
    {
        return SiteFixture::record('article', ['id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function page(string $id = 'page-1'): array
    {
        return SiteFixture::record('page', ['id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(string $id = 'prod-1'): array
    {
        return SiteFixture::record('product', ['id' => $id]);
    }

    public function testArticlesAreListedPageByPageAsModels(): void
    {
        $site = (new SiteRecorder())
            ->json(['data' => [self::article('art-1')], 'next_cursor' => 'c2'])
            ->json(['data' => [self::article('art-2')]]);

        $articles = iterator_to_array($site->provider->client()->api()->articles()->list(sort: 'published_at', order: 'desc', limit: 1), false);

        self::assertSame('GET /api/v1/articles?sort=published_at&order=desc&limit=1', $site->line(0));
        self::assertSame('GET /api/v1/articles?sort=published_at&order=desc&limit=1&cursor=c2', $site->line(1));
        self::assertSame('Bearer ' . FakeProvider::API_TOKEN, $site->request()->getHeaderLine('Authorization'));
        self::assertSame(['art-1', 'art-2'], array_map(static fn(Article $article): string => $article->id, $articles));
    }

    public function testAnArticleIsReadIntoItsModel(): void
    {
        $site = (new SiteRecorder())->json([...self::article('a/1'), 'added_later' => 'x']);

        $article = $site->provider->client()->api()->articles()->get('a/1');

        self::assertSame('GET /api/v1/articles/a%2F1', $site->line());
        self::assertSame('a/1', $article->id);
        self::assertSame('Release notes', $article->title);
        self::assertSame('release-notes', $article->slug);
        self::assertNull($article->keywords);
        self::assertSame(1, $article->status);
        self::assertNull($article->sort);
        self::assertEquals(new DateTimeImmutable('2026-10-01T09:00:00', new DateTimeZone('UTC')), $article->publishedAt);
        self::assertNull($article->expiredAt);
        self::assertSame('2026-09-30T16:20:00+00:00', $article->createdAt->format(DATE_ATOM));
        self::assertSame('2b4d6f8a-1c3e-4a5b-8d7f-9e0a1b2c3d4e', $article->categories[0]->id);
        self::assertSame('News', $article->categories[0]->title);
        self::assertSame('news', $article->categories[0]->slug);
        self::assertSame('x', $article->attributes['added_later']);
    }

    public function testAnArticleIsCreatedOnceUnderTheCallersKey(): void
    {
        $site = (new SiteRecorder())->json(self::article('art-9'), 201, ['Location' => FakeProvider::BASE_URL . '/api/v1/articles/art-9', 'Idempotent-Replayed' => 'true']);

        $created = $site->provider->client()->api()->articles()->create([
            'title' => 'Hello',
            'content' => '<p>Hi</p>',
            'published_at' => new DateTimeImmutable('2026-10-08T14:34:56+02:00'),
            'categories' => ['cat-1'],
        ], idempotencyKey: 'import-42');

        self::assertSame('POST /api/v1/articles', $site->line());
        self::assertSame('import-42', $site->request()->getHeaderLine('Idempotency-Key'));
        self::assertSame(['title' => 'Hello', 'content' => '<p>Hi</p>', 'published_at' => '2026-10-08T12:34:56Z', 'categories' => ['cat-1']], $site->body());
        self::assertSame('art-9', $created->value->id);
        self::assertSame('import-42', $created->idempotencyKey);
        self::assertTrue($created->replayed);
        self::assertSame(FakeProvider::BASE_URL . '/api/v1/articles/art-9', $created->response->location);
    }

    public function testACreateWithoutACallersKeyStillSendsOne(): void
    {
        $site = (new SiteRecorder())->json(self::article(), 201);

        $created = $site->provider->client()->api()->articles()->create(['title' => 'Hello', 'content' => 'Hi']);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/D', $site->request()->getHeaderLine('Idempotency-Key'));
        self::assertSame($site->request()->getHeaderLine('Idempotency-Key'), $created->idempotencyKey);
        self::assertFalse($created->replayed);
    }

    public function testAnArticleIsUpdatedWithOnlyTheChangedFields(): void
    {
        $site = (new SiteRecorder())->json(self::article());

        $article = $site->provider->client()->api()->articles()->update('art-1', ['title' => 'Hello again']);

        self::assertSame('PATCH /api/v1/articles/art-1', $site->line());
        self::assertFalse($site->request()->hasHeader('Idempotency-Key'));
        self::assertSame(['title' => 'Hello again'], $site->body());
        self::assertSame('art-1', $article->id);
    }

    public function testARefusedUpdateIsItsProblem(): void
    {
        $site = (new SiteRecorder())->problem(422, 'validation-failed', ['errors' => ['title' => ['Too long.']]]);

        $this->expectException(ValidationFailedException::class);

        $site->provider->client()->api()->articles()->update('art-1', ['title' => str_repeat('x', 300)]);
    }

    public function testPagesAreListedAndRead(): void
    {
        $site = (new SiteRecorder())->json(['data' => [self::page()]])->json(self::page('page-2'));
        $pages = $site->provider->client()->api()->pages();

        $listed = iterator_to_array($pages->list(), false);
        $page = $pages->get('page-2');

        self::assertSame('GET /api/v1/pages?limit=25', $site->line(0));
        self::assertSame('GET /api/v1/pages/page-2', $site->line(1));
        self::assertSame('page-1', $listed[0]->id);
        self::assertSame('About', $page->name);
        self::assertSame('About us', $page->title);
        self::assertSame('en', $page->language);
        self::assertNull($page->parentId);
        self::assertSame(1, $page->status);
        self::assertSame('2026-08-30T10:00:00+00:00', $page->createdAt?->format(DATE_ATOM));
        self::assertSame('2026-10-08T11:59:58+00:00', $page->updatedAt->format(DATE_ATOM));
    }

    public function testProductsAreListedAndReadWithTheirPricesInMinorUnits(): void
    {
        $site = (new SiteRecorder())->json(['data' => [self::product()], 'next_cursor' => 'c2'])->json(self::product('prod-2'));
        $products = $site->provider->client()->api()->products();

        $first = $products->list(order: 'asc')->page();
        $product = $products->get('prod-2');

        self::assertSame('GET /api/v1/products?order=asc&limit=25', $site->line(0));
        self::assertSame('c2', $first->nextCursor);
        self::assertSame('prod-1', $first->items[0]->id);
        self::assertSame('GET /api/v1/products/prod-2', $site->line(1));
        self::assertSame('subscription', $product->type);
        self::assertSame(1900, $product->price);
        self::assertNull($product->originalPrice);
        self::assertSame('USD', $product->currency);
        self::assertSame('EUR', $product->prices[1]->currency);
        self::assertSame(1700, $product->prices[1]->price);
        self::assertSame(1900, $product->prices[1]->originalPrice);
        self::assertSame(1, $product->status);
    }

    public function testAMissingRecordIsNotFound(): void
    {
        $site = (new SiteRecorder())->problem(404, 'not-found');

        $this->expectException(NotFoundException::class);

        $site->provider->client()->api()->products()->get('gone');
    }
}

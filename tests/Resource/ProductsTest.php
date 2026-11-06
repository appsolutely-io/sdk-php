<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\BackendApi;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Tests\Support\SiteFixture;
use PHPUnit\Framework\TestCase;

/**
 * The product catalogue, read by the site's backend.
 */
final class ProductsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    public static function product(string $id = 'prod-1'): array
    {
        return SiteFixture::record('product', ['id' => $id]);
    }

    public function testProductsAreListedAndReadWithTheirPricesInMinorUnits(): void
    {
        $site = (new SiteRecorder())->json(['data' => [self::product()], 'next_cursor' => 'c2'])->json(self::product('prod-2'));
        $products = self::backend($site)->products();

        $first = $products->list(order: 'asc')->page();
        $product = $products->get('prod-2');

        self::assertSame('GET /api/v1/products?order=asc&limit=25', $site->line(0));
        self::assertSame('Bearer machine-token', $site->request()->getHeaderLine('Authorization'));
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

    public function testAMissingProductIsNotFound(): void
    {
        $site = (new SiteRecorder())->problem(404, 'not-found');

        $this->expectException(NotFoundException::class);

        self::backend($site)->products()->get('gone');
    }

    private static function backend(SiteRecorder $site): BackendApi
    {
        $site->provider->token = static fn() => $site->provider->json(['access_token' => 'machine-token', 'token_type' => 'Bearer', 'expires_in' => 3600]);

        return $site->provider->backendClient()->backend();
    }
}

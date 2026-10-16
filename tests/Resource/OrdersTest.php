<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Tests\Support\SiteFixture;
use PHPUnit\Framework\TestCase;

/**
 * An order as the signed-in member reads one: money in minor units, the
 * status the site sends, and where to finish paying.
 */
final class OrdersTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    public static function order(string $id = 'ord-1'): array
    {
        return SiteFixture::record('order', ['id' => $id]);
    }

    public function testAnOrderIsReadWithMoneyInMinorUnits(): void
    {
        $site = (new SiteRecorder())->json(self::order('ord-2'));

        $order = self::api($site)->me()->orders()->get('ord-2');

        self::assertSame('GET /api/v1/me/orders/ord-2', $site->line());
        self::assertNull($order->subjectReference);
        self::assertSame('production', $order->mode);
        self::assertSame(1900, $order->amount);
        self::assertSame('USD', $order->currency);
        self::assertSame(0, $order->shippingAmount);
        self::assertSame(0, $order->taxAmount);
        self::assertTrue($order->taxIncluded);
        self::assertSame(1900, $order->totalAmount);
        self::assertSame('final', $order->totalShown);
        self::assertSame('team-plan', $order->items[0]->product);
        self::assertSame(1, $order->items[0]->quantity);
        self::assertSame(1900, $order->items[0]->price);
        self::assertSame('USD', $order->items[0]->currency);
    }

    public function testAnOrderStatusIsTheStringTheSiteSends(): void
    {
        $site = (new SiteRecorder())->json(SiteFixture::record('order', ['status' => 'shipped']))->json(SiteFixture::record('order', ['status' => null]));
        $orders = self::api($site)->me()->orders();

        self::assertSame('shipped', $orders->get('ord-1')->status);
        self::assertNull($orders->get('ord-2')->status);
    }

    public function testAnOrderAwaitingPaymentNamesWhereToFinishPaying(): void
    {
        $site = (new SiteRecorder())->json(SiteFixture::record('order', ['payment_url' => 'https://shop.example/orders/SO-1/pay']))->json(SiteFixture::record('order'));
        $orders = self::api($site)->me()->orders();

        self::assertSame('https://shop.example/orders/SO-1/pay', $orders->get('ord-1')->paymentUrl);
        self::assertNull($orders->get('ord-2')->paymentUrl);
    }

    public function testAnOrderWithoutAnUpdateTimeIsRefused(): void
    {
        $site = (new SiteRecorder())->json(SiteFixture::record('order', ['updated_at' => null]));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"updated_at"');

        self::api($site)->me()->orders()->get('ord-1');
    }

    public function testAMissingOrderIsNotFound(): void
    {
        $site = (new SiteRecorder())->problem(404, 'not-found');

        $this->expectException(NotFoundException::class);

        self::api($site)->me()->orders()->get('gone');
    }

    private static function api(SiteRecorder $site): MemberApi
    {
        return $site->provider->client()->forMember('member-access-token')->api();
    }
}

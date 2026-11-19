<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Tests\Support\SiteFixture;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testTheStatusFilterAndExistingPagingArgumentsAreSentOnEveryPage(): void
    {
        $site = (new SiteRecorder())->json(['data' => [], 'next_cursor' => 'next/page'])->json(['data' => [], 'next_cursor' => null]);
        $orders = self::api($site)->me()->orders()->list('created_at', 'desc', 10, status: 'paid');

        $orders->page();
        $orders->page('next/page');

        self::assertSame('sort=created_at&order=desc&status=paid&limit=10', $site->request()->getUri()->getQuery());
        self::assertSame('sort=created_at&order=desc&status=paid&limit=10&cursor=next%2Fpage', $site->request(1)->getUri()->getQuery());
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

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unpricedAmounts(): iterable
    {
        yield 'null tax' => [['tax_amount' => null]];
        yield 'null total' => [['total_amount' => null]];
        yield 'pending preparation' => [['tax_amount' => null, 'total_amount' => null, 'total_shown' => 'tax_preparation_pending']];
    }

    /** @param array<string, mixed> $amounts */
    #[DataProvider('unpricedAmounts')]
    public function testAnOrderAcceptsUnpricedAmounts(array $amounts): void
    {
        $record = [...self::order(), ...$amounts];
        $site = (new SiteRecorder())->json($record);

        $order = self::api($site)->me()->orders()->get('ord-1');

        self::assertSame($record['tax_amount'], $order->taxAmount);
        self::assertSame($record['total_amount'], $order->totalAmount);
        self::assertSame($record['total_shown'], $order->totalShown);
    }

    public function testAnOrderAcceptsOmittedUnpricedAmounts(): void
    {
        $record = self::order();
        unset($record['tax_amount'], $record['total_amount']);
        $site = (new SiteRecorder())->json($record);

        $order = self::api($site)->me()->orders()->get('ord-1');

        self::assertNull($order->taxAmount);
        self::assertNull($order->totalAmount);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountFields(): iterable
    {
        yield 'tax' => ['tax_amount'];
        yield 'total' => ['total_amount'];
    }

    #[DataProvider('amountFields')]
    public function testAnOrderRefusesMalformedAmounts(string $field): void
    {
        $site = (new SiteRecorder())->json([...self::order(), $field => 'unpriced']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"' . $field . '"');

        self::api($site)->me()->orders()->get('ord-1');
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

    public function testPaymentSwitchUsesTheSelectedMethodCurrentAttemptAndCallersKey(): void
    {
        $site = (new SiteRecorder())->json(SiteFixture::record('order', ['current_payment' => 'attempt-new', 'payment_methods' => [['id' => 'method-two', 'title' => 'Card']], 'attempts' => [['reference' => 'attempt-old', 'status' => 'failed', 'end_reason' => 'declined', 'end_reason_label' => 'Declined']]]));
        $answer = self::api($site)->me()->orders()->switchPaymentMethod('order/one', 'method-two', 'attempt-old', 'switch-once');

        self::assertSame('POST /api/v1/me/orders/order%2Fone/payment-method', $site->line());
        self::assertSame(['payment_method' => 'method-two', 'from_payment' => 'attempt-old'], $site->body());
        self::assertSame('switch-once', $answer->idempotencyKey);
        self::assertSame('attempt-new', $answer->value->currentPayment);
        self::assertNotNull($answer->value->paymentMethods);
        self::assertSame('method-two', $answer->value->paymentMethods[0]->id);
        self::assertSame('declined', $answer->value->attempts[0]->endReason);
    }

    public function testAPaymentSwitchWithoutACurrentAttemptOmitsItsReference(): void
    {
        $site = (new SiteRecorder())->json(self::order());
        self::api($site)->me()->orders()->switchPaymentMethod('order-one', 'method-two');
        self::assertSame(['payment_method' => 'method-two'], $site->body());
        self::assertNotSame('', $site->request()->getHeaderLine('Idempotency-Key'));
    }

    public function testAStalePaymentSwitchIsNotTreatedAsASuccess(): void
    {
        $site = (new SiteRecorder())->problem(409, 'order-payment-changed');
        $this->expectException(\Appsolutely\Sdk\Exception\ConflictException::class);
        self::api($site)->me()->orders()->switchPaymentMethod('order-one', 'method-two', 'attempt-old');
    }

    private static function api(SiteRecorder $site): MemberApi
    {
        return $site->provider->client()->forMember('member-access-token')->api();
    }
}

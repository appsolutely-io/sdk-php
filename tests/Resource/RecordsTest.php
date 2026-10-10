<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Exception\ConflictException;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Tests\Support\SiteFixture;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Orders, form entries, account states and webhook deliveries, read by the
 * administrator, and a delivery sent again.
 */
final class RecordsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    public static function order(string $id = 'ord-1'): array
    {
        return [
            'id' => $id,
            'subject_reference' => 'member-42',
            'coupon_code' => null,
            'status' => 2,
            'mode' => 'live',
            'subscription_start_state' => null,
            'subscription_start_refusal' => null,
            'summary' => 'Plan x1',
            'amount' => 1999,
            'currency' => 'USD',
            'discounted_amount' => 0,
            'shipping_amount' => 500,
            'tax_amount' => 160,
            'tax_included' => false,
            'total_amount' => 2659,
            'total_shown' => '$26.59',
            'note' => null,
            'created_at' => '2026-10-08T12:00:00Z',
            'updated_at' => '2026-10-08T12:34:56Z',
            'items' => [['id' => 'line-1', 'product_id' => 'prod-1', 'quantity' => 1, 'price' => 1999, 'currency' => 'USD']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formEntry(string $id = 'fe-1'): array
    {
        return SiteFixture::record('form-entry', ['id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function delivery(string $webhookId = 'msg_1'): array
    {
        return [
            'webhook_id' => $webhookId,
            'type' => 'order.paid',
            'occurred_at' => '2026-10-08T12:34:56Z',
            'status' => 'failed',
            'attempts' => 5,
            'last_response_status' => 500,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'failed_at' => '2026-10-09T12:34:56Z',
        ];
    }

    public function testOrdersAreListedAndReadWithMoneyInMinorUnits(): void
    {
        $site = (new SiteRecorder())->json(['data' => [self::order()]])->json(self::order('ord-2'));
        $orders = $site->provider->client()->api()->orders();

        $listed = iterator_to_array($orders->list(sort: 'updated_at', limit: 50), false);
        $order = $orders->get('ord-2');

        self::assertSame('GET /api/v1/orders?sort=updated_at&limit=50', $site->line(0));
        self::assertSame('GET /api/v1/orders/ord-2', $site->line(1));
        self::assertSame('ord-1', $listed[0]->id);
        self::assertSame('member-42', $order->subjectReference);
        self::assertSame(2, $order->status);
        self::assertSame('live', $order->mode);
        self::assertSame(1999, $order->amount);
        self::assertSame('USD', $order->currency);
        self::assertSame(500, $order->shippingAmount);
        self::assertSame(160, $order->taxAmount);
        self::assertFalse($order->taxIncluded);
        self::assertSame(2659, $order->totalAmount);
        self::assertSame('$26.59', $order->totalShown);
        self::assertSame('prod-1', $order->items[0]->productId);
        self::assertSame(1, $order->items[0]->quantity);
        self::assertSame(1999, $order->items[0]->price);
        self::assertSame('USD', $order->items[0]->currency);
    }

    public function testFormEntriesAreListedAndReadWithTheirAnswers(): void
    {
        $site = (new SiteRecorder())->json(['data' => [self::formEntry()], 'next_cursor' => 'c2'])->json(self::formEntry('fe-2'));
        $entries = $site->provider->client()->api()->formEntries();

        $page = $entries->list()->page();
        $entry = $entries->get('fe-2');

        self::assertSame('GET /api/v1/form-entries?limit=25', $site->line(0));
        self::assertSame('c2', $page->nextCursor);
        self::assertSame('GET /api/v1/form-entries/fe-2', $site->line(1));
        self::assertSame('contact', $entry->formSlug);
        self::assertSame('Ada', $entry->firstName);
        self::assertSame('ada@example.com', $entry->email);
        self::assertSame(['message' => 'Hello', 'topics' => ['billing']], $entry->data);
        self::assertFalse($entry->isSpam);
        self::assertSame('2026-10-08T11:59:57+00:00', $entry->submittedAt->format(DATE_ATOM));
    }

    public function testAccountStatesAreReadForTheSubjectsNamedInOnePage(): void
    {
        $site = (new SiteRecorder())->json(['data' => [SiteFixture::record('account-state', ['subject' => 'member-42'])]]);

        $page = $site->provider->client()->api()->accountStates()->list(['member-42', 'member-43']);

        self::assertSame('GET /api/v1/account-states?subjects%5B%5D=member-42&subjects%5B%5D=member-43', $site->line());
        self::assertFalse($page->hasMore());
        $state = $page->items[0];
        self::assertSame('member-42', $state->subject);
        self::assertSame(42, $state->sequence);
        self::assertSame('active', $state->status);
        self::assertTrue($state->emailVerified);
        self::assertSame('seats', $state->entitlements[0]->key);
        self::assertSame('2026-10-22T00:00:00+00:00', $state->entitlements[0]->expiresAt?->format(DATE_ATOM));
        self::assertNull($state->entitlements[1]->expiresAt);
        self::assertSame(['seats' => 3, 'reports.export' => 1], $state->totals);
    }

    public function testASubscriptionsDeliveriesAreListedByStatusAndTime(): void
    {
        $site = (new SiteRecorder())->json(['data' => [self::delivery()]]);

        $deliveries = iterator_to_array($site->provider->client()->api()->webhookDeliveries()->list('sub/1', status: 'failed', since: new DateTimeImmutable('2026-10-08T14:00:00+02:00')), false);

        self::assertSame('GET /api/v1/webhook-subscriptions/sub%2F1/deliveries?status=failed&since=2026-10-08T12%3A00%3A00Z&limit=25', $site->line());
        self::assertSame('msg_1', $deliveries[0]->webhookId);
        self::assertSame('order.paid', $deliveries[0]->type);
        self::assertSame(5, $deliveries[0]->attempts);
        self::assertSame(500, $deliveries[0]->lastResponseStatus);
        self::assertNull($deliveries[0]->deliveredAt);
        self::assertSame('2026-10-09T12:34:56+00:00', $deliveries[0]->failedAt?->format(DATE_ATOM));
    }

    public function testADeliveryIsRedeliveredOnceUnderAKey(): void
    {
        $site = (new SiteRecorder())->json([...self::delivery(), 'status' => 'pending'], 202);

        $result = $site->provider->client()->api()->webhookDeliveries()->redeliver('sub-1', 'msg_1', idempotencyKey: 'redeliver-msg_1');

        self::assertSame('POST /api/v1/webhook-subscriptions/sub-1/deliveries/msg_1/redeliver', $site->line());
        self::assertSame('redeliver-msg_1', $site->request()->getHeaderLine('Idempotency-Key'));
        self::assertSame('pending', $result->value->status);
        self::assertSame('redeliver-msg_1', $result->idempotencyKey);
    }

    public function testARedeliveryStillRunningUnderTheKeyIsRetriedThenThrown(): void
    {
        $site = (new SiteRecorder())
            ->problem(409, 'idempotency-request-in-flight', [])
            ->problem(409, 'idempotency-request-in-flight', [])
            ->problem(409, 'idempotency-request-in-flight', []);

        try {
            $site->provider->client()->api()->webhookDeliveries()->redeliver('sub-1', 'msg_1', idempotencyKey: 'k-1');
            self::fail('The refusal was not thrown.');
        } catch (ConflictException) {
        }

        $keys = array_map(static fn(\Psr\Http\Message\RequestInterface $request): string => $request->getHeaderLine('Idempotency-Key'), $site->provider->siteRequests());
        self::assertSame(['k-1', 'k-1', 'k-1'], $keys);
    }

    public function testAMissingOrderIsNotFound(): void
    {
        $site = (new SiteRecorder())->problem(404, 'not-found');

        $this->expectException(NotFoundException::class);

        $site->provider->client()->api()->orders()->get('gone');
    }
}

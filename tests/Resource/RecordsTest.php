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
        return SiteFixture::record('order', ['id' => $id]);
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
        self::assertNull($order->subjectReference);
        self::assertSame('production', $order->mode);
        self::assertSame(1900, $order->amount);
        self::assertSame('USD', $order->currency);
        self::assertSame(0, $order->shippingAmount);
        self::assertSame(0, $order->taxAmount);
        self::assertTrue($order->taxIncluded);
        self::assertSame(1900, $order->totalAmount);
        self::assertSame('final', $order->totalShown);
        self::assertSame('3a5c7e9b-1d3f-4b5d-8f1a-3c5e7a9b1d3f', $order->items[0]->productId);
        self::assertSame(1, $order->items[0]->quantity);
        self::assertSame(1900, $order->items[0]->price);
        self::assertSame('USD', $order->items[0]->currency);
    }

    public function testAnOrderStatusIsTheStringTheSiteSends(): void
    {
        $site = (new SiteRecorder())->json(SiteFixture::record('order', ['status' => 'shipped']))->json(SiteFixture::record('order', ['status' => null]));
        $orders = $site->provider->client()->api()->orders();

        self::assertSame('shipped', $orders->get('ord-1')->status);
        self::assertNull($orders->get('ord-2')->status);
    }

    public function testAnOrderNotYetUpdatedHasNoUpdateTime(): void
    {
        $site = (new SiteRecorder())->json(SiteFixture::record('order', ['updated_at' => null]));

        self::assertNull($site->provider->client()->api()->orders()->get('ord-1')->updatedAt);
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

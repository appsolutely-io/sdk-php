<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\BackendApi;
use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Exception\ConflictException;
use Appsolutely\Sdk\Model\EntitlementTerm;
use Appsolutely\Sdk\Model\ProductSkuPrice;
use Appsolutely\Sdk\Model\SubscriptionSource;
use Appsolutely\Sdk\Tests\Support\SiteFixture;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the backend's and the member's newer calls send and read: the body of
 * each write, the key of each keyed one, and the answer as a model.
 */
final class BackendTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function checkout(): array
    {
        return [
            'id' => 'cs_1',
            'url' => 'https://site.example.com/checkout/cs_1',
            'status' => 'open',
            'expires_at' => '2026-10-09T12:00:00Z',
            'mode' => 'production',
            'lines' => [['product' => 'mug', 'quantity' => 2]],
            'promotion_code' => null,
            'success_url' => 'https://app.example.com/thanks',
            'cancel_url' => 'https://app.example.com/cart',
            'order' => null,
            'may_still_bill' => [['billed_by' => 'app_store', 'manage_url' => 'https://apps.apple.com/account/subscriptions', 'subscription_id' => '4f6a8c0e-2b4d-4f6a-8c0e-2b4d6f8a0c2e']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function endpoint(): array
    {
        return [
            'id' => '12',
            'url' => 'https://app.example.com/hooks',
            'event_types' => ['order.paid'],
            'is_active' => true,
            'rotation_state' => null,
            'rotation_started_at' => null,
            'last_delivered_at' => null,
            'last_failed_at' => null,
            'created_at' => '2026-10-08T12:00:00Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function delivery(string $endpoint): array
    {
        return [
            'endpoint_id' => $endpoint,
            'webhook_id' => 'msg_1',
            'type' => 'order.paid',
            'occurred_at' => '2026-10-08T12:00:00Z',
            'status' => 'pending',
            'attempts' => 0,
            'last_response_status' => null,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
        ];
    }

    public function testACheckoutIsStartedForTheMemberNamedWithAKey(): void
    {
        $site = (new SiteRecorder())->json(self::checkout(), 201);

        $result = self::backend($site)->checkoutSessions()->create('member-sub', [['product' => 'mug', 'quantity' => 2]], 'https://app.example.com/thanks', 'https://app.example.com/cart', idempotencyKey: 'cart-9');

        self::assertSame('POST /api/v1/checkout-sessions', $site->line());
        self::assertSame('"cart-9"', $site->request()->getHeaderLine('Idempotency-Key'));
        self::assertSame(['subject' => 'member-sub', 'lines' => [['product' => 'mug', 'quantity' => 2]], 'success_url' => 'https://app.example.com/thanks', 'cancel_url' => 'https://app.example.com/cart', 'promotion_code' => null], $site->body());
        self::assertSame('https://site.example.com/checkout/cs_1', $result->value->url);
        self::assertSame('mug', $result->value->lines[0]->product);
        self::assertNull($result->value->orderReference);
        self::assertSame(SubscriptionSource::BILLED_BY_APP_STORE, $result->value->mayStillBill[0]->billedBy);
    }

    public function testACheckoutLineMayNameTheSkuItBuys(): void
    {
        $site = (new SiteRecorder())->json([...self::checkout(), 'lines' => [['product' => 'team', 'quantity' => 3, 'sku' => 'team-yearly'], ['product' => 'mug', 'quantity' => 1]]], 201);

        $result = self::backend($site)->checkoutSessions()->create('member-sub', [['product' => 'team', 'quantity' => 3, 'sku' => 'team-yearly'], ['product' => 'mug', 'quantity' => 1]], 'https://app.example.com/thanks', 'https://app.example.com/cart');

        self::assertSame([['product' => 'team', 'quantity' => 3, 'sku' => 'team-yearly'], ['product' => 'mug', 'quantity' => 1]], $site->body()['lines'] ?? null);
        self::assertSame('team-yearly', $result->value->lines[0]->sku);
        self::assertNull($result->value->lines[1]->sku);
        self::assertSame([], $result->value->lines[0]->extra);
    }

    public function testAPurchaseRefusedForAWaitingOrderSaysWhereItIsPaid(): void
    {
        $site = (new SiteRecorder())->problem(409, 'subscription-awaiting-payment', ['order' => 'SO-20261008-0042', 'payment_url' => 'https://site.example.com/orders/SO-20261008-0042/pay']);

        try {
            self::backend($site)->checkoutSessions()->create('member-sub', [['product' => 'team', 'quantity' => 1]], 'https://app.example.com/thanks', 'https://app.example.com/cart');
            self::fail('The refusal was not thrown.');
        } catch (ConflictException $exception) {
            self::assertTrue($exception->hasType('subscription-awaiting-payment'));
            self::assertSame('SO-20261008-0042', $exception->extension('order'));
            self::assertSame('https://site.example.com/orders/SO-20261008-0042/pay', $exception->extension('payment_url'));
        }
    }

    public function testAMembersCheckoutNamesNoSubject(): void
    {
        $site = (new SiteRecorder())->json([...self::checkout(), 'order' => ['reference' => 'SO-1']]);

        $result = self::member($site)->me()->checkoutSessions()->create([['product' => 'mug', 'quantity' => 1]], 'https://app.example.com/thanks', 'https://app.example.com/cart', 'SPRING');

        self::assertSame('POST /api/v1/me/checkout-sessions', $site->line());
        self::assertSame(['lines' => [['product' => 'mug', 'quantity' => 1]], 'success_url' => 'https://app.example.com/thanks', 'cancel_url' => 'https://app.example.com/cart', 'promotion_code' => 'SPRING'], $site->body());
        self::assertNotSame('', $site->request()->getHeaderLine('Idempotency-Key'));
        self::assertSame('SO-1', $result->value->orderReference);
    }

    public function testAStorePurchaseLeavesOutWhatTheStoreDoesNotUse(): void
    {
        $site = (new SiteRecorder())->json(['outcome' => 'recorded', 'belongs_to_you' => true, 'subscription_id' => '9', 'status' => 'active', 'kind' => 'subscription', 'environment' => 'sandbox']);

        $transaction = self::backend($site)->storePurchases()->transaction('member-sub', 'app_store', 'member', signedTransaction: 'eyJ.signed');

        self::assertSame('POST /api/v1/store-purchases/transactions', $site->line());
        self::assertSame(['subject' => 'member-sub', 'store' => 'app_store', 'initiation' => 'member', 'signed_transaction' => 'eyJ.signed'], $site->body());
        self::assertSame('sandbox', $transaction->environment);
    }

    public function testATestClockIsAdvancedByTheStepNamed(): void
    {
        $site = (new SiteRecorder())->json(['id' => '9', 'status' => 'active', 'current_period_end' => '2026-11-08T00:00:00Z', 'clock_now' => '2026-10-09T12:00:00Z']);

        $result = self::backend($site)->testClocks()->advance('9', 86400);

        self::assertSame('POST /api/v1/subscriptions/9/test-clock/advance', $site->line());
        self::assertSame(['seconds' => 86400], $site->body());
        self::assertSame('2026-10-09T12:00:00+00:00', $result->value->clockNow->format(DATE_ATOM));
    }

    public function testANewEndpointsSecretIsKeptOutOfADump(): void
    {
        $site = (new SiteRecorder())->json([...self::endpoint(), 'secret' => 'whsec_c2VjcmV0LXNlY3JldC1zZWNyZXQtc2VjcmV0'], 201);

        $created = self::backend($site)->webhookEndpoints()->create('https://app.example.com/hooks', ['order.paid'])->value;

        self::assertSame(['url' => 'https://app.example.com/hooks', 'event_types' => ['order.paid']], $site->body());
        self::assertSame('whsec_c2VjcmV0LXNlY3JldC1zZWNyZXQtc2VjcmV0', $created->secret);
        self::assertSame(['order.paid'], $created->endpoint->eventTypes);
        self::assertStringNotContainsString('whsec_', print_r($created, true));
    }

    public function testARotationIsCompletedByItsId(): void
    {
        $site = (new SiteRecorder())->json(self::endpoint());

        self::backend($site)->webhookEndpoints()->completeRotation('12', 'rot-1');

        self::assertSame('POST /api/v1/webhook-endpoints/12/secret-rotations/complete', $site->line());
        self::assertSame(['confirm' => 'rot-1'], $site->body());
    }

    public function testARedeliveryNamesTheEndpointsThatRefusedIt(): void
    {
        $site = (new SiteRecorder())->json(['data' => [self::delivery('12')], 'refused' => [['endpoint_id' => '13', 'reason' => 'The endpoint is switched off.']]], 202);

        $redelivery = self::backend($site)->deliveries()->redeliver('msg_1')->value;

        self::assertSame('POST /api/v1/deliveries/msg_1/redeliveries', $site->line());
        self::assertSame('12', $redelivery->redelivered[0]->endpointId);
        self::assertSame('msg_1', $redelivery->redelivered[0]->webhookId);
        self::assertSame(['13' => 'The endpoint is switched off.'], $redelivery->refused);
    }

    public function testAFeatureValueIsPutWhateverItsType(): void
    {
        $site = (new SiteRecorder())->json(['key' => 'reports.export', 'kind' => 'boolean', 'value' => true, 'retired' => false]);

        $value = self::backend($site)->featureValues()->set('team', 'reports.export', true);

        self::assertSame('PUT /api/v1/products/team/feature-values/reports.export', $site->line());
        self::assertSame(['value' => true], $site->body());
        self::assertTrue($value->value);
    }

    public function testARoleIsAssignedByPuttingIt(): void
    {
        $site = (new SiteRecorder())->json(['subject' => 'member-sub', 'roles' => ['editor']]);

        $held = self::backend($site)->roleAssignments()->assign('member-sub', 'editor');

        self::assertSame('PUT /api/v1/role-assignments/member-sub/editor', $site->line());
        self::assertSame(['editor'], $held->roles);
    }

    public function testASeatIsGrantedInThePayersPool(): void
    {
        $site = (new SiteRecorder())->json(['id' => '5', 'pool' => '9', 'member' => 'member-sub', 'label' => null, 'granted_at' => '2026-10-08T12:00:00Z', 'passing' => false], 201);

        $seat = self::backend($site)->seats()->grant('9', 'payer-sub', 'member-sub');

        self::assertSame('POST /api/v1/seats', $site->line());
        self::assertSame(['pool' => '9', 'payer' => 'payer-sub', 'member' => 'member-sub', 'label' => null], $site->body());
        self::assertSame('9', $seat->pool);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function seatMembers(): iterable
    {
        yield 'null member' => [['member' => null]];
        yield 'omitted member' => [[]];
        yield 'named member' => [['member' => 'member-sub']];
    }

    /** @param array<string, mixed> $member */
    #[DataProvider('seatMembers')]
    public function testASeatPoolAcceptsAnUnavailableMember(array $member): void
    {
        $seat = [...$member, 'id' => '5', 'pool' => '9', 'label' => null, 'granted_at' => '2026-10-08T12:00:00Z', 'passing' => false];
        $site = (new SiteRecorder())->json(['data' => [['id' => '9', 'product' => null, 'quantity' => 1, 'scheduled' => 1, 'ceiling' => 1, 'occupied' => 1, 'seats' => [$seat]]]]);

        $pools = self::backend($site)->seats()->pools('payer-sub');

        self::assertCount(1, $pools->items);
        self::assertSame(1, $pools->items[0]->occupied);
        self::assertCount(1, $pools->items[0]->seats);
        self::assertSame($member['member'] ?? null, $pools->items[0]->seats[0]->member);
        self::assertSame('5', $pools->items[0]->seats[0]->id);
    }

    public function testAnAccountStateIsReadByItsSubject(): void
    {
        /** @var array<string, mixed> $answer */
        $answer = json_decode(SiteFixture::json('account-state'), true, 512, JSON_THROW_ON_ERROR);
        $site = (new SiteRecorder())->json($answer);

        $state = self::backend($site)->accountStates()->get('member-sub');

        self::assertSame('GET /api/v1/entitlements/member-sub', $site->line());
        self::assertSame(EntitlementTerm::SOURCE_PLAN, $state->entitlements[0]->breakdown[0]->source ?? null);
    }

    public function testTheAccountStatesWalkStartsFromAMomentAndNamesWhereTheNextOneStarts(): void
    {
        /** @var array<string, mixed> $state */
        $state = json_decode(SiteFixture::json('account-state'), true, 512, JSON_THROW_ON_ERROR);
        $site = (new SiteRecorder())
            ->json(['data' => [[...$state, 'entitlements_changed_at' => '2026-10-08T12:00:00Z']], 'next_cursor' => 'c2', 'next_changed_since' => '2026-10-08T12:30:00Z'])
            ->json(['data' => [], 'next_changed_since' => '2026-10-08T12:30:00Z']);

        $pages = iterator_to_array(self::backend($site)->accountStates()->list(new DateTimeImmutable('2026-10-08T13:00:00+01:00'), 50), false);

        self::assertSame('GET /api/v1/entitlements?changed_since=2026-10-08T12%3A00%3A00Z&limit=50', $site->line());
        self::assertSame('GET /api/v1/entitlements?changed_since=2026-10-08T12%3A00%3A00Z&limit=50&cursor=c2', $site->line(1));
        self::assertCount(2, $pages);
        self::assertSame('c2', $pages[0]->nextCursor);
        self::assertNull($pages[1]->nextCursor);
        self::assertSame('2026-10-08T12:30:00+00:00', $pages[0]->nextChangedSince->format(DATE_ATOM));
        self::assertCount(1, $pages[0]->states);
        self::assertSame(42, $pages[0]->states[0]->state->sequence);
        self::assertSame([], $pages[0]->states[0]->state->extra);
        self::assertSame('2026-10-08T12:00:00+00:00', $pages[0]->states[0]->entitlementsChangedAt->format(DATE_ATOM));
    }

    public function testAWalkWithoutAMomentListsEveryone(): void
    {
        $site = (new SiteRecorder())->json(['data' => [], 'next_changed_since' => '2026-10-08T12:30:00Z']);

        iterator_to_array(self::backend($site)->accountStates()->list());

        self::assertSame('GET /api/v1/entitlements?limit=25', $site->line());
    }

    public function testTheBackendReadsTheCatalogueWithItsOwnToken(): void
    {
        $site = (new SiteRecorder())->json(SiteFixture::record('product'));

        $product = self::backend($site)->products()->get('team-plan');

        self::assertSame('GET /api/v1/products/team-plan', $site->line());
        self::assertSame('Bearer machine-token', $site->request()->getHeaderLine('Authorization'));
        self::assertSame('team-yearly', $product->skuPrices[1]->sku);
        self::assertSame(ProductSkuPrice::INTERVAL_YEAR, $product->skuPrices[1]->interval);
        self::assertSame(1, $product->skuPrices[1]->intervalCount);
        self::assertSame(22800, $product->skuPrices[1]->originalPrice);
    }

    public function testTheBackendClientNamesTheCurrencyCheckoutChargesIn(): void
    {
        $site = (new SiteRecorder())->json(['client_id' => 'machine', 'mode' => 'production', 'site' => 'shop', 'scopes' => ['catalogue:read'], 'store_currency' => 'EUR']);

        self::assertSame('EUR', self::backend($site)->client()->storeCurrency);
    }

    public function testAMemberChangesASubscriptionByPlanOrQuantity(): void
    {
        $site = (new SiteRecorder())->json(['applied' => false, 'subscription' => null, 'confirmation' => ['id' => 'ps_1', 'url' => 'https://site.example.com/billing/ps_1', 'status' => 'open', 'expires_at' => '2026-10-08T13:00:00Z', 'mode' => 'production', 'return_url' => 'https://app.example.com/account']]);

        $change = self::member($site)->me()->subscriptions()->change('9', 'https://app.example.com/account', quantity: 5)->value;

        self::assertSame('POST /api/v1/me/subscriptions/9/changes', $site->line());
        self::assertSame(['sku' => null, 'quantity' => 5, 'return_url' => 'https://app.example.com/account'], $site->body());
        self::assertFalse($change->applied);
        self::assertSame('https://site.example.com/billing/ps_1', $change->confirmation?->url);
    }

    public function testAChangeHandedToTheMemberToConfirmIsReadFromItsAccepted(): void
    {
        $site = (new SiteRecorder())->json(['applied' => false, 'subscription' => null, 'confirmation' => ['id' => 'ps_1', 'url' => 'https://site.example.com/billing/ps_1', 'status' => 'open', 'expires_at' => '2026-10-08T13:00:00Z', 'mode' => 'production', 'return_url' => 'https://app.example.com/account']], 202);

        $change = self::member($site)->me()->subscriptions()->change('9', 'https://app.example.com/account', 'team')->value;

        self::assertSame(['sku' => 'team', 'quantity' => null, 'return_url' => 'https://app.example.com/account'], $site->body());
        self::assertSame('https://site.example.com/billing/ps_1', $change->confirmation?->url);
    }

    public function testAResumptionSendsWhereTheMemberComesBackTo(): void
    {
        $site = (new SiteRecorder())->json(['applied' => false, 'subscription' => null, 'confirmation' => ['id' => 'ps_2', 'url' => 'https://site.example.com/billing/ps_2', 'status' => 'open', 'expires_at' => '2026-10-08T13:00:00Z', 'mode' => 'production', 'return_url' => 'https://app.example.com/account']], 202);

        $resumption = self::member($site)->me()->subscriptions()->resume('9', 'https://app.example.com/account')->value;

        self::assertSame('POST /api/v1/me/subscriptions/9/resumption', $site->line());
        self::assertSame(['return_url' => 'https://app.example.com/account'], $site->body());
        self::assertNull($resumption->subscription);
        self::assertSame('https://site.example.com/billing/ps_2', $resumption->confirmation?->url);
    }

    public function testAStoreIntentNamesEachSubscriptionStillBilledByItsSource(): void
    {
        $site = (new SiteRecorder())->json(['store' => 'app_store', 'store_product_id' => 'team.monthly', 'account_token' => '0b1c2d3e-4f5a-4b6c-8d7e-9f0a1b2c3d4e', 'replaces_purchase_token' => null, 'may_still_bill' => [['billed_by' => 'site', 'manage_url' => null, 'subscription_id' => '4f6a8c0e-2b4d-4f6a-8c0e-2b4d6f8a0c2e']]]);

        $intent = self::member($site)->me()->storePurchases()->intent('app_store', 'team');

        self::assertSame(SubscriptionSource::BILLED_BY_SITE, $intent->mayStillBill[0]->billedBy);
        self::assertNull($intent->mayStillBill[0]->manageUrl);
        self::assertSame('4f6a8c0e-2b4d-4f6a-8c0e-2b4d6f8a0c2e', $intent->mayStillBill[0]->subscriptionId);
    }

    public function testASeatRemovalCanSelectAPoolWithoutChangingUnscopedCalls(): void
    {
        $site = (new SiteRecorder())->empty(204)->empty(204);
        $seats = self::backend($site)->seats();
        $seats->take('member', 'pool/1');
        $seats->take('member');
        self::assertSame('DELETE /api/v1/seats/member?pool=pool%2F1', $site->line(0));
        self::assertSame('DELETE /api/v1/seats/member', $site->line(1));
        self::assertSame('', $site->request(0)->getHeaderLine('Idempotency-Key'));
    }

    private static function backend(SiteRecorder $site): BackendApi
    {
        $site->provider->token = static fn() => $site->provider->json(['access_token' => 'machine-token', 'token_type' => 'Bearer', 'expires_in' => 3600]);

        return $site->provider->backendClient()->backend();
    }

    private static function member(SiteRecorder $site): MemberApi
    {
        return $site->provider->client()->forMember('member-token')->api();
    }
}

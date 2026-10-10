<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Exception\ApiException;
use PHPUnit\Framework\TestCase;

/**
 * The member's own data, every call carrying the member's access token.
 */
final class MemberTest extends TestCase
{
    private const string TOKEN = 'member-access-token';

    /**
     * @return array<string, mixed>
     */
    public static function address(string $id = 'addr-1'): array
    {
        return [
            'id' => $id,
            'name' => 'Ada Lovelace',
            'mobile' => '+44 20 0000 0000',
            'address' => '1 Main St',
            'address_extra' => null,
            'town' => null,
            'city' => 'London',
            'province' => null,
            'postcode' => 'N1 1AA',
            'country' => 'GB',
            'note' => null,
            'remark' => null,
            'sort' => 0,
            'created_at' => '2026-10-08T12:00:00Z',
            'updated_at' => null,
        ];
    }

    public function testTheMemberIsReadAndRenamedWithTheirToken(): void
    {
        $member = ['id' => 'member-42', 'name' => 'Ada', 'email' => 'ada@example.com', 'email_verified_at' => '2026-10-01T00:00:00Z', 'created_at' => null, 'updated_at' => null];
        $site = (new SiteRecorder())->json($member)->json([...$member, 'name' => 'Ada L.']);

        $me = self::api($site)->me();
        $read = $me->get();
        $renamed = $me->update('Ada L.');

        self::assertSame('GET /api/v1/me', $site->line(0));
        self::assertSame('Bearer ' . self::TOKEN, $site->request(0)->getHeaderLine('Authorization'));
        self::assertSame('member-42', $read->id);
        self::assertSame('ada@example.com', $read->email);
        self::assertSame('2026-10-01T00:00:00+00:00', $read->emailVerifiedAt?->format(DATE_ATOM));
        self::assertSame('PATCH /api/v1/me', $site->line(1));
        self::assertSame(['name' => 'Ada L.'], $site->body(1));
        self::assertSame('Ada L.', $renamed->name);
    }

    public function testAddressesAreListedFiledChangedAndDeleted(): void
    {
        $site = (new SiteRecorder())
            ->json(['data' => [self::address()]])
            ->json(self::address('addr-2'), 201)
            ->json([...self::address('addr-2'), 'city' => 'Leeds'])
            ->empty(204);
        $addresses = self::api($site)->me()->addresses();

        $listed = iterator_to_array($addresses->list(order: 'desc'), false);
        $filed = $addresses->create(['name' => 'Ada Lovelace', 'address' => '1 Main St', 'country' => 'GB'], idempotencyKey: 'address-form-7');
        $changed = $addresses->update('addr-2', ['city' => 'Leeds']);
        $addresses->delete('addr-2');

        self::assertSame('GET /api/v1/me/addresses?order=desc&limit=25', $site->line(0));
        self::assertSame('addr-1', $listed[0]->id);
        self::assertSame('London', $listed[0]->city);
        self::assertSame('N1 1AA', $listed[0]->postcode);
        self::assertSame('POST /api/v1/me/addresses', $site->line(1));
        self::assertSame('address-form-7', $site->request(1)->getHeaderLine('Idempotency-Key'));
        self::assertSame(['name' => 'Ada Lovelace', 'address' => '1 Main St', 'country' => 'GB'], $site->body(1));
        self::assertSame('addr-2', $filed->value->id);
        self::assertSame('address-form-7', $filed->idempotencyKey);
        self::assertSame('PATCH /api/v1/me/addresses/addr-2', $site->line(2));
        self::assertSame('Leeds', $changed->city);
        self::assertSame('DELETE /api/v1/me/addresses/addr-2', $site->line(3));
    }

    public function testTheMembersOrdersAreListedAndRead(): void
    {
        $site = (new SiteRecorder())->json(['data' => [RecordsTest::order()]])->json(RecordsTest::order('ord-2'));
        $orders = self::api($site)->me()->orders();

        $listed = iterator_to_array($orders->list(), false);
        $order = $orders->get('ord-2');

        self::assertSame('GET /api/v1/me/orders?limit=25', $site->line(0));
        self::assertSame('ord-1', $listed[0]->id);
        self::assertSame('GET /api/v1/me/orders/ord-2', $site->line(1));
        self::assertSame(2659, $order->totalAmount);
    }

    public function testTheMembersEntitlementsAreOnePage(): void
    {
        $site = (new SiteRecorder())->json(['data' => [['key' => 'pro', 'label' => 'Pro', 'quantity' => 1, 'expires_at' => null]]]);

        $page = self::api($site)->me()->entitlements()->list();

        self::assertSame('GET /api/v1/me/entitlements', $site->line());
        self::assertSame('pro', $page->items[0]->key);
        self::assertSame(1, $page->items[0]->quantity);
        self::assertNull($page->items[0]->expiresAt);
        self::assertFalse($page->hasMore());
    }

    public function testTheBillingEntryIsReadForAStore(): void
    {
        $site = (new SiteRecorder())->json(['available' => true, 'wording' => 'Manage billing', 'presentation' => 'link', 'needs_store_token' => false, 'url' => 'https://site.example.com/billing/x', 'expires_at' => '2026-10-08T13:00:00Z']);

        $entry = self::api($site)->me()->billingEntry()->get('app_store', storefront: 'GB');

        self::assertSame('GET /api/v1/me/billing-entry?store=app_store&storefront=GB', $site->line());
        self::assertTrue($entry->available);
        self::assertSame('Manage billing', $entry->wording);
        self::assertSame('link', $entry->presentation);
        self::assertFalse($entry->needsStoreToken);
        self::assertSame('https://site.example.com/billing/x', $entry->url);
        self::assertSame('2026-10-08T13:00:00+00:00', $entry->expiresAt?->format(DATE_ATOM));
    }

    public function testAPushDeviceIsRegisteredAndUnregistered(): void
    {
        $site = (new SiteRecorder())
            ->json(['token' => 'apns-token', 'platform' => 'ios', 'provider' => 'apns', 'app_identifier' => 'com.example.app', 'installation_id' => null, 'last_used_at' => null])
            ->empty(204);
        $devices = self::api($site)->me()->pushDevices();

        $device = $devices->register('apns-token', 'ios', appIdentifier: 'com.example.app', metadata: ['locale' => 'en']);
        $devices->unregister('apns-token');

        self::assertSame('POST /api/v1/me/push-devices', $site->line(0));
        self::assertSame(['token' => 'apns-token', 'platform' => 'ios', 'app_identifier' => 'com.example.app', 'metadata' => ['locale' => 'en']], $site->body(0));
        self::assertSame('ios', $device->platform);
        self::assertSame('apns', $device->provider);
        self::assertSame('com.example.app', $device->appIdentifier);
        self::assertSame('POST /api/v1/me/push-devices/unregister', $site->line(1));
        self::assertSame(['token' => 'apns-token'], $site->body(1));
    }

    public function testTheReferralAndItsRewardsAreRead(): void
    {
        $site = (new SiteRecorder())
            ->json([
                'code' => 'ADA-10',
                'programme' => 'friends',
                'discount' => ['type' => 'percent', 'value' => 10, 'max_discount' => 1000, 'currency' => 'USD'],
                'min_order_amount' => 2000,
                'reward' => ['type' => 'credit', 'value' => 500, 'currency' => 'USD'],
            ])
            ->json(['data' => [['id' => 'rw-1', 'type' => 'credit', 'state' => 'held', 'value' => 500, 'currency' => 'USD', 'hold_until' => '2026-11-01T00:00:00Z', 'code' => null, 'expires_at' => null, 'earned_at' => '2026-10-08T12:00:00Z']], 'next_cursor' => 'c2']);
        $referral = self::api($site)->me()->referral();

        $read = $referral->get();
        $rewards = $referral->rewards(limit: 10)->page();

        self::assertSame('GET /api/v1/me/referral', $site->line(0));
        self::assertSame('ADA-10', $read->code);
        self::assertSame('friends', $read->programme);
        self::assertSame('percent', $read->discount->type);
        self::assertSame(10, $read->discount->value);
        self::assertSame(1000, $read->discount->maxDiscount);
        self::assertSame(2000, $read->minOrderAmount);
        self::assertSame(500, $read->reward->value);
        self::assertSame('USD', $read->reward->currency);
        self::assertSame('GET /api/v1/me/referral/rewards?limit=10', $site->line(1));
        self::assertSame('held', $rewards->items[0]->state);
        self::assertSame('2026-11-01T00:00:00+00:00', $rewards->items[0]->holdUntil?->format(DATE_ATOM));
        self::assertSame('c2', $rewards->nextCursor);
    }

    public function testTheMembersSyncFeedsCarryTheirToken(): void
    {
        $site = (new SiteRecorder())
            ->json(['upserts' => [self::address()], 'tombstones' => [], 'next_cursor' => 'a', 'has_more' => false])
            ->json(['results' => [['mutation_id' => 'm-1', 'id' => 'addr-1', 'applied' => true, 'server_record' => self::address()]]])
            ->json(['upserts' => [RecordsTest::order()], 'tombstones' => [], 'next_cursor' => 'b', 'has_more' => false]);
        $sync = self::api($site)->sync();

        $addresses = $sync->addresses()->pull();
        $pushed = $sync->addresses()->push([['mutation_id' => 'm-1', 'op' => 'delete', 'id' => 'addr-1', 'client_updated_at' => '2026-10-08T12:00:00Z']]);
        $orders = $sync->orders()->pull('w-1');

        self::assertSame('GET /api/v1/sync/me-addresses', $site->line(0));
        self::assertSame('London', $addresses->upserts[0]->city);
        self::assertSame('POST /api/v1/sync/me-addresses', $site->line(1));
        self::assertSame('addr-1', $pushed->results[0]->serverRecord?->id);
        self::assertSame('GET /api/v1/sync/me-orders?cursor=w-1', $site->line(2));
        self::assertSame('ord-1', $orders->upserts[0]->id);
        foreach ($site->provider->siteRequests() as $request) {
            self::assertSame('Bearer ' . self::TOKEN, $request->getHeaderLine('Authorization'));
        }
    }

    public function testAReferralTheMemberHasNoneOfIsItsProblem(): void
    {
        $site = (new SiteRecorder())->problem(404, 'referral-unavailable');

        try {
            self::api($site)->me()->referral()->get();
            self::fail('The refusal was not thrown.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->hasType('referral-unavailable'));
        }
    }

    private static function api(SiteRecorder $site): MemberApi
    {
        return $site->provider->client()->forMember(self::TOKEN)->api();
    }
}

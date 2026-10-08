<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Webhooks;

use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Tests\Support\FrozenClock;
use Appsolutely\Sdk\Webhooks\Deliveries;
use PHPUnit\Framework\TestCase;

final class DeliveriesTest extends TestCase
{
    public function testAnIdIsHandledOnlyOnceTheIntegratorMarksIt(): void
    {
        $deliveries = new Deliveries(new InMemoryCache(new FrozenClock()));

        self::assertFalse($deliveries->wasHandled('msg_1'));
        $deliveries->markHandled('msg_1');
        self::assertTrue($deliveries->wasHandled('msg_1'));
        self::assertFalse($deliveries->wasHandled('msg_2'));
    }

    /**
     * Three days: past the sender's retry schedule of about 28 hours and a
     * manual redelivery soon after it.
     */
    public function testByDefaultAnIdIsRememberedForThreeDays(): void
    {
        $clock = new FrozenClock();
        $deliveries = new Deliveries(new InMemoryCache($clock));
        $deliveries->markHandled('msg_1');

        $clock->advance(3 * 86400 - 1);
        self::assertTrue($deliveries->wasHandled('msg_1'));

        $clock->advance(1);
        self::assertFalse($deliveries->wasHandled('msg_1'));
    }

    public function testTheRetentionIsConfigurable(): void
    {
        $clock = new FrozenClock();
        $deliveries = new Deliveries(new InMemoryCache($clock), 3600);
        $deliveries->markHandled('msg_1');

        $clock->advance(3600);

        self::assertFalse($deliveries->wasHandled('msg_1'));
    }

    public function testAnIdWithCharactersPsr16ReservesIsStillUsable(): void
    {
        $deliveries = new Deliveries(new InMemoryCache(new FrozenClock()));

        $deliveries->markHandled('msg:{weird}/id@x');

        self::assertTrue($deliveries->wasHandled('msg:{weird}/id@x'));
    }

    /**
     * Two endpoints (two apps) sharing one cache can receive the same id;
     * a namespace each keeps one from skipping a delivery the other handled.
     */
    public function testDeliveriesInDifferentNamespacesDoNotShareHandledIds(): void
    {
        $cache = new InMemoryCache(new FrozenClock());
        $orders = new Deliveries($cache, namespace: 'orders-app');
        $billing = new Deliveries($cache, namespace: 'billing-app');

        $orders->markHandled('msg_1');

        self::assertTrue($orders->wasHandled('msg_1'));
        self::assertFalse($billing->wasHandled('msg_1'));
        self::assertFalse((new Deliveries($cache))->wasHandled('msg_1'));
    }

    public function testANamespaceAndAnIdDoNotRunTogether(): void
    {
        $cache = new InMemoryCache(new FrozenClock());

        (new Deliveries($cache, namespace: 'a'))->markHandled('bc');

        self::assertFalse((new Deliveries($cache, namespace: 'ab'))->wasHandled('c'));
    }

    /**
     * Ids handled before namespaces existed stay handled under the default.
     */
    public function testTheDefaultNamespaceKeepsTheKeysOfIdsAlreadyHandled(): void
    {
        $cache = new InMemoryCache(new FrozenClock());

        (new Deliveries($cache))->markHandled('msg_1');

        self::assertTrue($cache->has('appsolutely.webhooks.handled.' . hash('sha256', 'msg_1')));
        self::assertTrue((new Deliveries($cache, namespace: ''))->wasHandled('msg_1'));
    }

    public function testANonPositiveRetentionIsRefused(): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        new Deliveries(new InMemoryCache(new FrozenClock()), 0);
    }
}

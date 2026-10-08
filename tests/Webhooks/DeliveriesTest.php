<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Webhooks;

use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Exception\InvalidConfigException;
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

    public function testByDefaultAnIdIsRememberedLongerThanTheSendersRetrySpan(): void
    {
        $clock = new FrozenClock();
        $deliveries = new Deliveries(new InMemoryCache($clock));
        $deliveries->markHandled('msg_1');

        $clock->advance(48 * 3600);

        self::assertTrue($deliveries->wasHandled('msg_1'));
        self::assertGreaterThan(28 * 3600, Deliveries::DEFAULT_TTL);
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

    public function testANonPositiveRetentionIsRefused(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Deliveries(new InMemoryCache(new FrozenClock()), 0);
    }
}

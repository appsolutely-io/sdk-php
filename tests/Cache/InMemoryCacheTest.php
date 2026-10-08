<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Cache;

use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Tests\Support\FrozenClock;
use DateInterval;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

final class InMemoryCacheTest extends TestCase
{
    public function testAValueLivesUntilItsTtlRunsOut(): void
    {
        $clock = new FrozenClock();
        $cache = new InMemoryCache($clock);

        $cache->set('a', 'one', 10);
        $cache->set('b', 'two', new DateInterval('PT20S'));
        $cache->set('c', 'three');
        $clock->advance(10);

        self::assertNull($cache->get('a'));
        self::assertFalse($cache->has('a'));
        self::assertSame('two', $cache->get('b'));
        self::assertSame(['b' => 'two', 'c' => 'three', 'x' => 'default'], $cache->getMultiple(['b', 'c', 'x'], 'default'));
    }

    public function testANonPositiveTtlDeletesTheValue(): void
    {
        $cache = new InMemoryCache(new FrozenClock());
        $cache->set('a', 'one');

        $cache->set('a', 'two', 0);

        self::assertFalse($cache->has('a'));
    }

    public function testAReservedCharacterInAKeyIsRefusedAsPsr16Requires(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InMemoryCache(new FrozenClock()))->get('a:b');
    }
}

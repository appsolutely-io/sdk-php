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
    private FrozenClock $clock;
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->cache = new InMemoryCache($this->clock);
    }

    public function testAValueWithASecondsTtlLivesUntilTheTtlRunsOut(): void
    {
        $this->cache->set('a', 'one', 10);

        $this->clock->advance(9);
        self::assertSame('one', $this->cache->get('a'));

        $this->clock->advance(1);
        self::assertNull($this->cache->get('a'));
        self::assertFalse($this->cache->has('a'));
    }

    public function testAValueWithAnIntervalTtlLivesUntilTheIntervalRunsOut(): void
    {
        $this->cache->set('b', 'two', new DateInterval('PT20S'));

        $this->clock->advance(19);
        self::assertSame('two', $this->cache->get('b'));

        $this->clock->advance(1);
        self::assertFalse($this->cache->has('b'));
    }

    public function testAValueWithoutATtlDoesNotExpire(): void
    {
        $this->cache->set('c', 'three');

        $this->clock->advance(10 * 365 * 86400);

        self::assertSame('three', $this->cache->get('c'));
    }

    public function testGetMultipleAnswersTheDefaultForAMissingKey(): void
    {
        $this->cache->set('b', 'two');

        self::assertSame(['b' => 'two', 'x' => 'default'], $this->cache->getMultiple(['b', 'x'], 'default'));
    }

    public function testANonPositiveTtlDeletesTheValue(): void
    {
        $this->cache->set('a', 'one');

        $this->cache->set('a', 'two', 0);

        self::assertFalse($this->cache->has('a'));
    }

    public function testAReservedCharacterInAKeyIsRefusedAsPsr16Requires(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->get('a:b');
    }
}

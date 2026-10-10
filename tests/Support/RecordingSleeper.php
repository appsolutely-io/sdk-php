<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

use Appsolutely\Sdk\Clock\Sleeper;

/**
 * Records each wait instead of waiting, and moves the clock on by it.
 */
final class RecordingSleeper implements Sleeper
{
    /** @var list<float> */
    public array $sleeps = [];

    public function __construct(private readonly ?FrozenClock $clock = null) {}

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->clock?->advance((int) ceil($seconds));
    }
}

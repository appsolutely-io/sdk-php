<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

/**
 * One quota the site states for the caller: what `RateLimit-Policy` says of
 * it and what `RateLimit` says is left. A number the site did not send, or
 * sent in a form that is not a non-negative integer, is null.
 */
final readonly class RateLimitQuota
{
    /**
     * @param string $name the quota's name, such as `api:authenticated`
     * @param int|null $quota how many requests the window allows (`q`)
     * @param int|null $window the window's length in seconds (`w`)
     * @param int|null $remaining how many requests are left in the current window (`r`)
     * @param int|null $reset seconds until the current window turns over (`t`)
     */
    public function __construct(
        public string $name,
        public ?int $quota = null,
        public ?int $window = null,
        public ?int $remaining = null,
        public ?int $reset = null,
    ) {}
}

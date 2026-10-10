<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * The billing period a member is now inside: the one to key a term to.
 */
final readonly class SubscriptionPeriod
{
    /**
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self($json->time('start'), $json->time('end'), $json->extra());
    }
}

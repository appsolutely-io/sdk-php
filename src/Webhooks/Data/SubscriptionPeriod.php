<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * The billing period a member is now inside: the one to key a term to.
 */
final readonly class SubscriptionPeriod
{
    /**
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self($fields->time('start'), $fields->time('end'), $fields->extra());
    }
}

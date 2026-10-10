<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * A period opened by a renewal or an upgrade and not yet settled. It has no
 * end on purpose: a term keyed to money must not be read from a period still
 * in flight.
 */
final readonly class PendingPeriod
{
    /**
     * @param string $status where the period's payment stands, such as `pending` or `settling`
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public DateTimeImmutable $start,
        public string $status,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self($fields->time('start'), $fields->string('status'), $fields->extra());
    }
}

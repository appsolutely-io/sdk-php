<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

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
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public DateTimeImmutable $start,
        public string $status,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self($json->time('start'), $json->string('status'), $json->extra());
    }
}

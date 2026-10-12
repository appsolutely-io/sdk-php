<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A seat in a pool, held by one member.
 */
final readonly class Seat
{
    /** @internal */
    public const string SCHEMA = 'Seat';

    /**
     * @param string $pool the id of the subscription the pool is
     * @param string $member the holder's public name
     * @param bool $passing whether the seat is about to leave its holder, at the pool's next renewal
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $pool,
        public string $member,
        public ?string $label,
        public DateTimeImmutable $grantedAt,
        public bool $passing,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            pool: $json->string('pool'),
            member: $json->string('member'),
            label: $json->nullableString('label'),
            grantedAt: $json->time('granted_at'),
            passing: $json->bool('passing'),
            extra: $json->extra(),
        );
    }
}

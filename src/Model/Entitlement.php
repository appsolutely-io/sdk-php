<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * Something an account may use now, such as a plan or a number of seats.
 * What granted it is not sent.
 */
final readonly class Entitlement
{
    /** @internal */
    public const string SCHEMA = 'Entitlement';

    /**
     * @param DateTimeImmutable|null $expiresAt null when nothing on the site dates its end
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $quantity,
        public ?DateTimeImmutable $expiresAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            key: $json->string('key'),
            label: $json->string('label'),
            quantity: $json->int('quantity'),
            expiresAt: $json->nullableTime('expires_at'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * One thing an account may use now. What granted it is not sent.
 */
final readonly class Entitlement
{
    /**
     * @param DateTimeImmutable|null $expiresAt null when nothing on the site dates its end
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $quantity,
        public ?DateTimeImmutable $expiresAt,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('key'),
            $fields->string('label'),
            $fields->int('quantity'),
            $fields->nullableTime('expires_at'),
            $fields->extra(),
        );
    }
}

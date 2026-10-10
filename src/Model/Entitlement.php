<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * Something a member holds the right to, such as a plan or a number of
 * seats, until it expires.
 */
final readonly class Entitlement
{
    /** @internal */
    public const string SCHEMA = 'Entitlement';

    /**
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $quantity,
        public ?DateTimeImmutable $expiresAt,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            key: $json->string('key'),
            label: $json->string('label'),
            quantity: $json->int('quantity'),
            expiresAt: $json->optionalTime('expires_at'),
            attributes: $json->all(),
        );
    }
}

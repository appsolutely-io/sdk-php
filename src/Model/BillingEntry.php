<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * Where the member manages their billing for one store, if anywhere.
 */
final readonly class BillingEntry
{
    /** @internal */
    public const string SCHEMA = 'BillingEntry';

    /**
     * @param string|null $wording the label to show for the entry
     * @param string|null $presentation how to present it, such as `link`
     * @param string|null $url where the member manages their billing; it expires at $expiresAt
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public bool $available,
        public ?string $wording,
        public ?string $presentation,
        public ?bool $needsStoreToken,
        public ?string $url,
        public ?DateTimeImmutable $expiresAt,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            available: $json->bool('available'),
            wording: $json->optionalString('wording'),
            presentation: $json->optionalString('presentation'),
            needsStoreToken: $json->optionalBool('needs_store_token'),
            url: $json->optionalString('url'),
            expiresAt: $json->optionalTime('expires_at'),
            attributes: $json->all(),
        );
    }
}

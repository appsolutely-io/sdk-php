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
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public bool $available,
        public ?string $wording,
        public ?string $presentation,
        public ?bool $needsStoreToken,
        public ?string $url,
        public ?DateTimeImmutable $expiresAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            available: $json->bool('available'),
            wording: $json->nullableString('wording'),
            presentation: $json->nullableString('presentation'),
            needsStoreToken: $json->nullableBool('needs_store_token'),
            url: $json->nullableString('url'),
            expiresAt: $json->nullableTime('expires_at'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The update a desktop app built with Tauri is offered, in the shape the
 * Tauri updater reads.
 */
final readonly class TauriUpdateManifest implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'TauriUpdateManifest';

    /**
     * @param string $version the version offered
     * @param string|null $pubDate when it was published, as the updater reads it (RFC 3339)
     * @param string|null $url where the update bundle is downloaded from
     * @param string|null $signature the bundle's updater signature
     * @param string|null $minimumSystemVersion the oldest system version the update runs on
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $version,
        public ?string $notes,
        public ?string $pubDate,
        public ?string $url,
        public ?string $signature,
        public ?string $minimumSystemVersion,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            version: $json->string('version'),
            notes: $json->nullableString('notes'),
            pubDate: $json->nullableString('pub_date'),
            url: $json->nullableString('url'),
            signature: $json->nullableString('signature'),
            minimumSystemVersion: $json->nullableString('minimum_system_version'),
            extra: $json->extra(),
        );
    }
}

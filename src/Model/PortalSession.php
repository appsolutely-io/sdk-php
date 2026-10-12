<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A visit to the site's billing pages for one member, and where it stands.
 */
final readonly class PortalSession
{
    /** @internal */
    public const string SCHEMA = 'PortalSession';

    /**
     * @param string|null $url where to send the member, or null once the visit expired
     * @param string $returnUrl where the member is sent back to
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $url,
        public string $status,
        public DateTimeImmutable $expiresAt,
        public string $mode,
        public string $returnUrl,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            url: $json->nullableString('url'),
            status: $json->string('status'),
            expiresAt: $json->time('expires_at'),
            mode: $json->string('mode'),
            returnUrl: $json->string('return_url'),
            extra: $json->extra(),
        );
    }
}

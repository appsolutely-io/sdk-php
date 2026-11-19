<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class MessagePreference implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'MessagePreference';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $category,
        public string $channel,
        public bool $enabled,
        public bool $changeable,
        public bool $consentRequired,
        public ?\DateTimeImmutable $consentedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            category: $json->string('category'),
            channel: $json->string('channel'),
            enabled: $json->bool('enabled'),
            changeable: $json->bool('changeable'),
            consentRequired: $json->bool('consent_required'),
            consentedAt: $json->nullableTime('consented_at'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

/**
 * A category an article is filed under: enough to link or group by, not the
 * whole category.
 */
final readonly class ArticleCategory
{
    /**
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('id'),
            $fields->string('title'),
            $fields->nullableString('slug'),
            $fields->extra(),
        );
    }
}

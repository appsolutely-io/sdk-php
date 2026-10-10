<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A category an article is filed under: enough to link or group by, not the
 * whole category.
 */
final readonly class ArticleCategory
{
    /** @internal */
    public const string SCHEMA = 'ArticleCategorySummary';

    /**
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            title: $json->string('title'),
            slug: $json->nullableString('slug'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A category an article is filed under.
 */
final readonly class ArticleCategory
{
    /** @internal */
    public const string SCHEMA = 'ArticleCategorySummary';

    /**
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            title: $json->string('title'),
            slug: $json->optionalString('slug'),
            attributes: $json->all(),
        );
    }
}

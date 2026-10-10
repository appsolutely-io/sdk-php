<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An article of the site's content.
 */
final readonly class Article
{
    /** @internal */
    public const string SCHEMA = 'Article';

    /**
     * @param int $status 1 published, 0 a draft
     * @param list<ArticleCategory> $categories
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public ?string $description,
        public ?string $keywords,
        public ?string $cover,
        public int $status,
        public ?int $sort,
        public DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiredAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $categories,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            title: $json->string('title'),
            slug: $json->optionalString('slug'),
            description: $json->optionalString('description'),
            keywords: $json->optionalString('keywords'),
            cover: $json->optionalString('cover'),
            status: $json->int('status'),
            sort: $json->optionalInt('sort'),
            publishedAt: $json->time('published_at'),
            expiredAt: $json->optionalTime('expired_at'),
            createdAt: $json->time('created_at'),
            updatedAt: $json->time('updated_at'),
            categories: array_map(ArticleCategory::from(...), $json->objects('categories')),
            attributes: $json->all(),
        );
    }
}

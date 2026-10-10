<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An article of the site's content, as an API answer and an `article.*`
 * delivery both carry it.
 */
final readonly class Article
{
    /** @internal */
    public const string SCHEMA = 'Article';

    /**
     * @param string|null $cover the absolute URL the cover image is served at
     * @param int $status 1 when published, 0 when a draft
     * @param list<ArticleCategory> $categories every category it is filed under; empty when none
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
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
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            title: $json->string('title'),
            slug: $json->nullableString('slug'),
            description: $json->nullableString('description'),
            keywords: $json->nullableString('keywords'),
            cover: $json->nullableString('cover'),
            status: $json->int('status'),
            sort: $json->nullableInt('sort'),
            publishedAt: $json->time('published_at'),
            expiredAt: $json->nullableTime('expired_at'),
            createdAt: $json->time('created_at'),
            updatedAt: $json->time('updated_at'),
            categories: array_map(ArticleCategory::from(...), $json->objects('categories')),
            extra: $json->extra(),
        );
    }
}

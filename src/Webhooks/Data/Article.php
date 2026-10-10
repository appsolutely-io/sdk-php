<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * An article as the site's REST API serves it.
 */
final readonly class Article
{
    /**
     * @param string|null $cover the absolute URL the cover image is served at
     * @param int $status 1 when active, 0 when not
     * @param list<ArticleCategory>|null $categories null when the site did not describe them
     * @param array<string, mixed> $extra fields the site sent that this class does not name
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
        public ?array $categories,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        $categories = $fields->nullableObjects('categories');

        return new self(
            $fields->string('id'),
            $fields->string('title'),
            $fields->nullableString('slug'),
            $fields->nullableString('description'),
            $fields->nullableString('keywords'),
            $fields->nullableString('cover'),
            $fields->int('status'),
            $fields->nullableInt('sort'),
            $fields->time('published_at'),
            $fields->nullableTime('expired_at'),
            $fields->time('created_at'),
            $fields->time('updated_at'),
            $categories === null ? null : array_map(ArticleCategory::read(...), $categories),
            $fields->extra(),
        );
    }
}

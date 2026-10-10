<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A page of the site's content (the document's `Page` schema), as an API
 * answer and a `page.*` delivery both carry it, named so it is not mistaken
 * for Api\Page, a page of a list.
 */
final readonly class ContentPage
{
    /** @internal */
    public const string SCHEMA = 'Page';

    /**
     * @param string|null $parentId the id of the page this one sits under
     * @param int|null $status 1 when active, 0 when not
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $title,
        public ?string $slug,
        public ?string $description,
        public ?string $keywords,
        public ?string $language,
        public ?string $parentId,
        public ?int $status,
        public DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiredAt,
        public ?DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            name: $json->nullableString('name'),
            title: $json->nullableString('title'),
            slug: $json->nullableString('slug'),
            description: $json->nullableString('description'),
            keywords: $json->nullableString('keywords'),
            language: $json->nullableString('language'),
            parentId: $json->nullableString('parent_id'),
            status: $json->nullableInt('status'),
            publishedAt: $json->time('published_at'),
            expiredAt: $json->nullableTime('expired_at'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->time('updated_at'),
            extra: $json->extra(),
        );
    }
}

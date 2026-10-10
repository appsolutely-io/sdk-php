<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A page of the site's content (the document's `Page` schema), named so it
 * is not mistaken for Api\Page, a page of a list.
 */
final readonly class ContentPage
{
    /** @internal */
    public const string SCHEMA = 'Page';

    /**
     * @param string|null $parentId the id of the page this one sits under
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
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
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            name: $json->optionalString('name'),
            title: $json->optionalString('title'),
            slug: $json->optionalString('slug'),
            description: $json->optionalString('description'),
            keywords: $json->optionalString('keywords'),
            language: $json->optionalString('language'),
            parentId: $json->optionalString('parent_id'),
            status: $json->optionalInt('status'),
            publishedAt: $json->time('published_at'),
            expiredAt: $json->optionalTime('expired_at'),
            createdAt: $json->optionalTime('created_at'),
            updatedAt: $json->time('updated_at'),
            attributes: $json->all(),
        );
    }
}

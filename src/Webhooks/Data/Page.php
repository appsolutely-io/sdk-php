<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * A page as the site's REST API serves it.
 */
final readonly class Page
{
    /**
     * @param string|null $parentId the id of the page this one sits under
     * @param int|null $status 1 when active, 0 when not
     * @param array<string, mixed> $extra fields the site sent that this class does not name
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

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('id'),
            $fields->nullableString('name'),
            $fields->nullableString('title'),
            $fields->nullableString('slug'),
            $fields->nullableString('description'),
            $fields->nullableString('keywords'),
            $fields->nullableString('language'),
            $fields->nullableString('parent_id'),
            $fields->nullableInt('status'),
            $fields->time('published_at'),
            $fields->nullableTime('expired_at'),
            $fields->nullableTime('created_at'),
            $fields->time('updated_at'),
            $fields->extra(),
        );
    }
}

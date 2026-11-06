<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Model;

use Appsolutely\Sdk\Model\Fields;
use DateTimeImmutable;

/**
 * An article as the Administrator API answers it.
 */
final readonly class Article implements AdminModel
{
    /** @internal */
    public const string SCHEMA = 'Article';

    public const int STATUS_PUBLISHED = 1;
    public const int STATUS_DRAFT = 0;

    /**
     * @param string $id its id
     * @param int $status `STATUS_PUBLISHED` or `STATUS_DRAFT`
     * @param ?string $frontendUrl where the article is read on the site
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public ?int $sort,
        public DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiredAt,
        public int $status,
        public ?string $cover,
        public ?string $description,
        public ?string $keywords,
        public ?string $content,
        public ?string $frontendUrl,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): static
    {
        return new self(
            id: $json->string('id'),
            title: $json->string('title'),
            slug: $json->nullableString('slug'),
            sort: $json->nullableInt('sort'),
            publishedAt: $json->time('published_at'),
            expiredAt: $json->nullableTime('expired_at'),
            status: $json->int('status'),
            cover: $json->nullableString('cover'),
            description: $json->nullableString('description'),
            keywords: $json->nullableString('keywords'),
            content: $json->nullableString('content'),
            frontendUrl: $json->nullableString('frontend_url'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            extra: $json->extra(),
        );
    }
}

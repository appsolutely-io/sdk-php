<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Model;

use Appsolutely\Sdk\Model\Fields;
use DateTimeImmutable;

/**
 * A page as the Administrator API answers it.
 */
final readonly class Page implements AdminModel
{
    /** @internal */
    public const string SCHEMA = 'Page';

    public const int STATUS_PUBLISHED = 1;
    public const int STATUS_DRAFT = 0;

    /**
     * @param int $status `STATUS_PUBLISHED` or `STATUS_DRAFT`
     * @param ?string $parentId the id of the page it sits under
     * @param ?string $frontendUrl where the page is read on the site
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public ?string $name,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiredAt,
        public int $status,
        public ?string $description,
        public ?string $keywords,
        public ?string $parentId,
        public ?string $reference,
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
            name: $json->nullableString('name'),
            publishedAt: $json->nullableTime('published_at'),
            expiredAt: $json->nullableTime('expired_at'),
            status: $json->int('status'),
            description: $json->nullableString('description'),
            keywords: $json->nullableString('keywords'),
            parentId: $json->nullableString('parent_id'),
            reference: $json->nullableString('reference'),
            frontendUrl: $json->nullableString('frontend_url'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            extra: $json->extra(),
        );
    }
}

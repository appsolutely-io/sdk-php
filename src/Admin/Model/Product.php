<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Model;

use Appsolutely\Sdk\Model\Fields;
use DateTimeImmutable;

/**
 * A product as the Administrator API answers it.
 */
final readonly class Product implements AdminModel
{
    /** @internal */
    public const string SCHEMA = 'Product';

    public const int STATUS_PUBLISHED = 1;
    public const int STATUS_DRAFT = 0;
    public const string TYPE_PHYSICAL = 'physical';
    public const string TYPE_AUTO_VIRTUAL = 'auto_virtual';
    public const string TYPE_MANUAL_VIRTUAL = 'manual_virtual';
    public const string TYPE_SUBSCRIPTION = 'subscription';
    public const string TYPE_VOUCHER = 'voucher';

    /**
     * @param string $type one of the `TYPE_*` constants
     * @param int $status `STATUS_PUBLISHED` or `STATUS_DRAFT`
     * @param ?bool $perSeat whether it is sold per seat
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $status,
        public string $title,
        public ?string $subtitle,
        public ?string $slug,
        public string $content,
        public ?string $description,
        public ?string $keywords,
        public ?string $cover,
        public ?int $sort,
        public ?bool $perSeat,
        public ?string $planFamily,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiredAt,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): static
    {
        return new self(
            id: $json->string('id'),
            type: $json->string('type'),
            status: $json->int('status'),
            title: $json->string('title'),
            subtitle: $json->nullableString('subtitle'),
            slug: $json->nullableString('slug'),
            content: $json->string('content'),
            description: $json->nullableString('description'),
            keywords: $json->nullableString('keywords'),
            cover: $json->nullableString('cover'),
            sort: $json->nullableInt('sort'),
            perSeat: $json->nullableBool('per_seat'),
            planFamily: $json->nullableString('plan_family'),
            publishedAt: $json->nullableTime('published_at'),
            expiredAt: $json->nullableTime('expired_at'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            extra: $json->extra(),
        );
    }
}

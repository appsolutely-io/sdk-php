<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class Announcement implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'Announcement';

    /**
     * @param array<array-key, mixed>|null $data
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $reference,
        public string $category,
        public string $title,
        public string $body,
        public ?string $link,
        public ?array $data,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            reference: $json->string('reference'),
            category: $json->string('category'),
            title: $json->string('title'),
            body: $json->string('body'),
            link: $json->nullableString('link'),
            data: $json->nullableMap('data'),
            startsAt: $json->time('starts_at'),
            endsAt: $json->time('ends_at'),
            extra: $json->extra(),
        );
    }
}

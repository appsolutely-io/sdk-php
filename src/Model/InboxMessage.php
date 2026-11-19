<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class InboxMessage implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'InboxMessage';

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
        public string $sequence,
        public bool $read,
        public \DateTimeImmutable $receivedAt,
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
            sequence: $json->string('sequence'),
            read: $json->bool('read'),
            receivedAt: $json->time('received_at'),
            extra: $json->extra(),
        );
    }
}

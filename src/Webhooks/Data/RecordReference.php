<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

/**
 * A record the site's REST API has no representation for, named by its
 * reference and its kind and nothing else.
 */
final readonly class RecordReference
{
    /**
     * @param string $type the kind of record, such as `Refund`
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public ?string $id,
        public string $type,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self($fields->nullableString('id'), $fields->string('type'), $fields->extra());
    }
}

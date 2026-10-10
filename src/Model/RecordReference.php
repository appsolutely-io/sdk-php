<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A record the site's REST API has no representation for, named by its
 * reference and its kind and nothing else.
 */
final readonly class RecordReference
{
    /**
     * @param string $type the kind of record, such as `Refund`
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public ?string $id,
        public string $type,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self($json->nullableString('id'), $json->string('type'), $json->extra());
    }
}

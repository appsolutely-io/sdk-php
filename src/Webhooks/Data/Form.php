<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

/**
 * The form an entry was submitted through.
 */
final readonly class Form
{
    /**
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public string $name,
        public string $slug,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self($fields->string('name'), $fields->string('slug'), $fields->extra());
    }
}

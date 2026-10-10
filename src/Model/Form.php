<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The form an entry was submitted through.
 */
final readonly class Form
{
    /**
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $name,
        public string $slug,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self($json->string('name'), $json->string('slug'), $json->extra());
    }
}

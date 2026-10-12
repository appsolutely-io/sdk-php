<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

use Appsolutely\Sdk\Model\Fields;

/**
 * The reader for a record the document leaves an open object, such as the
 * server's copy a sync push answers with: it takes the members as decoded,
 * so the model around it is held to its own fields and nothing else.
 */
final readonly class UntypedRecord
{
    /**
     * @param array<array-key, mixed> $members
     */
    private function __construct(public array $members) {}

    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self($json->extra());
    }
}

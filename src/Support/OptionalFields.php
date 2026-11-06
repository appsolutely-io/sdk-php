<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Support;

/** @internal */
final class OptionalFields
{
    private function __construct() {}

    /**
     * @template K of array-key
     * @template T
     * @param array<K, T|null> $fields
     * @return array<K, T>
     */
    public static function withoutNulls(#[\SensitiveParameter] array $fields): array
    {
        return array_filter($fields, static fn(mixed $value): bool => $value !== null);
    }
}

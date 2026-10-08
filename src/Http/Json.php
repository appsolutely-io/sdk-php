<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use JsonException;

/**
 * @internal
 */
final class Json
{
    /**
     * A JSON object as an associative array, or null for anything else.
     *
     * @return array<string, mixed>|null
     */
    public static function decodeObject(string $json): ?array
    {
        try {
            $value = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            return null;
        }

        return self::stringKeys($value);
    }

    /**
     * A decoded JSON object with its keys as strings: json_decode() turns a
     * key such as "1" into an integer, which the array<string, mixed> type
     * of every object this client reads does not allow.
     *
     * @param array<mixed> $object
     * @return array<string, mixed>
     */
    public static function stringKeys(array $object): array
    {
        $strings = [];
        foreach ($object as $key => $member) {
            $strings[(string) $key] = $member;
        }

        return $strings;
    }
}

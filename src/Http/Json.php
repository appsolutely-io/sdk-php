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
     * A JSON object as an associative array, or null for anything else. A
     * member named like a decimal integer has an integer key: PHP turns
     * such a key into an integer whatever it is cast to.
     *
     * @return array<array-key, mixed>|null
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

        return $value;
    }
}

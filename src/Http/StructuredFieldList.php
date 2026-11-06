<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

/**
 * A Structured Field list (RFC 9651 section 4.2) whose members are strings or
 * tokens with parameters, the shape of the rate-limit fields. Inner lists and
 * other kinds of item are not needed and fail the parse.
 *
 * @internal
 */
final class StructuredFieldList
{
    private const string TOKEN = '[A-Za-z*][A-Za-z0-9:\/!#$%&\'*+\-.^_`|~]*';
    private const string KEY = '[a-z*][a-z0-9_\-.*]*';

    /**
     * The members in order, each as its item and its parameters, or null
     * when the field does not parse: RFC 9651 has a field that fails to parse
     * ignored whole, never read in part.
     *
     * @return list<array{string, array<string, int|float|string|bool>}>|null
     */
    public static function parse(string $field): ?array
    {
        $offset = 0;
        $length = strlen($field);
        $members = [];

        self::skip($field, $offset, " \t");
        if ($offset === $length) {
            return [];
        }

        while (true) {
            $item = self::bareItem($field, $offset, itemOnly: true);
            if (!is_string($item)) {
                return null;
            }

            $parameters = [];
            while ($offset < $length && $field[$offset] === ';') {
                $offset++;
                self::skip($field, $offset, ' ');
                if (preg_match('/\G' . self::KEY . '/', $field, $match, 0, $offset) !== 1) {
                    return null;
                }
                $offset += strlen($match[0]);
                $value = true;
                if ($offset < $length && $field[$offset] === '=') {
                    $offset++;
                    $value = self::bareItem($field, $offset, itemOnly: false);
                    if ($value === null) {
                        return null;
                    }
                }
                $parameters[$match[0]] = $value;
            }
            $members[] = [$item, $parameters];

            self::skip($field, $offset, " \t");
            if ($offset === $length) {
                return $members;
            }
            if ($field[$offset] !== ',') {
                return null;
            }
            $offset++;
            self::skip($field, $offset, " \t");
            if ($offset === $length) {
                return null;
            }
        }
    }

    /**
     * A string or a token for an item; for a parameter value also an
     * integer, a decimal, a boolean or a byte sequence (kept as its Base64).
     */
    private static function bareItem(string $field, int &$offset, bool $itemOnly): int|float|string|bool|null
    {
        if (preg_match('/\G"((?:[\x20\x21\x23-\x5B\x5D-\x7E]|\\\\["\\\\])*)"/', $field, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return (string) preg_replace('/\\\\(["\\\\])/', '$1', $match[1]);
        }
        if (preg_match('/\G' . self::TOKEN . '/', $field, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return $match[0];
        }
        if ($itemOnly) {
            return null;
        }
        if (preg_match('/\G-?\d{1,12}\.\d{1,3}(?![\d.])/', $field, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return (float) $match[0];
        }
        if (preg_match('/\G-?\d{1,15}(?![\d.])/', $field, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return (int) $match[0];
        }
        if (preg_match('/\G\?([01])/', $field, $match, 0, $offset) === 1) {
            $offset += 2;

            return $match[1] === '1';
        }
        if (preg_match('/\G:([A-Za-z0-9+\/=]*):/', $field, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return $match[1];
        }

        return null;
    }

    private static function skip(string $field, int &$offset, string $characters): void
    {
        $offset += strspn($field, $characters, $offset);
    }
}

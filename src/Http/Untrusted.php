<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

/**
 * A value from outside (an unverified token header, an error a redirect or a
 * provider sent back) made safe to put in an exception message or a log
 * entry: printable ASCII only, so a line break cannot forge a log line and an
 * escape sequence cannot reach a terminal, and short, so a megabyte header
 * cannot flood a log.
 *
 * @internal
 */
final class Untrusted
{
    public const int MAX_LENGTH = 64;

    public static function text(string $value, int $maxLength = self::MAX_LENGTH): string
    {
        $printable = (string) preg_replace('/[^\x20-\x7E]/', '?', $value);

        return strlen($printable) > $maxLength
            ? substr($printable, 0, $maxLength) . '...'
            : $printable;
    }
}

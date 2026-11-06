<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * @internal
 */
final class RetryAfter
{
    /**
     * The seconds a `Retry-After` field asks to wait (RFC 9110 section
     * 10.2.3): a count of seconds, or an HTTP date counted from now and never
     * below zero. Null when the field is absent or neither.
     */
    public static function seconds(string $value, DateTimeInterface $now): ?int
    {
        $value = trim($value);
        if (preg_match('/^\d{1,10}$/D', $value) === 1) {
            return (int) $value;
        }

        $date = DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $value, new DateTimeZone('UTC'));
        if ($date === false) {
            return null;
        }

        return max(0, $date->getTimestamp() - $now->getTimestamp());
    }
}

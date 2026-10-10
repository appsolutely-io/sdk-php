<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Support;

/**
 * @internal
 */
final class Uuid
{
    /**
     * A random UUID (RFC 9562 section 5.4), from the CSPRNG: an idempotency
     * key another caller could guess would let them replay its answer.
     */
    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class CacheControl
{
    /**
     * How long a response may be kept, in seconds; zero means not at all.
     *
     * Only `no-store` and `max-age` are honoured. `no-cache` alone falls back
     * to the default, because Laravel stamps `no-cache, private` on every
     * response that does not set its own header, which says nothing about how
     * fresh a discovery document or key set is.
     */
    public static function ttl(ResponseInterface $response, int $fallback): int
    {
        $directives = array_map(
            static fn(string $directive): string => strtolower(trim($directive)),
            explode(',', $response->getHeaderLine('Cache-Control')),
        );

        if (in_array('no-store', $directives, true)) {
            return 0;
        }

        foreach ($directives as $directive) {
            if (preg_match('/^max-age\s*=\s*"?(\d+)"?$/', $directive, $match) === 1) {
                return (int) $match[1];
            }
        }

        return $fallback;
    }
}

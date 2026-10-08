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
     * The longest a response is kept, whatever its max-age says: a day. The
     * key set is the reason. A key the provider withdraws (rotated out or
     * compromised) stays trusted for as long as the cached set lives, so a
     * mistaken or hostile max-age of months would keep a dead key valid for
     * months; a day bounds that while still sparing the provider a request
     * per sign-in.
     */
    public const int MAX_TTL = 86400;

    /**
     * How long a response may be kept, in seconds; zero means not at all,
     * and never more than MAX_TTL.
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
                return min((int) $match[1], self::MAX_TTL);
            }
        }

        return min($fallback, self::MAX_TTL);
    }
}

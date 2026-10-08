<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

/**
 * The one rule for every URL this client sends a secret to or takes keys
 * from: HTTPS, except on a loopback host, where a developer's local server
 * has no certificate and nothing leaves the machine (RFC 8252 section 8.3).
 *
 * Only the three literal loopback forms count. A `*.localhost` name is
 * reserved for loopback (RFC 6761 section 6.3), but whether it resolves
 * there depends on the resolver in use, so it is held to HTTPS like any
 * other name.
 *
 * @internal
 */
final class SecureUrl
{
    private const array LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    public static function isAbsolute(string $url): bool
    {
        $parts = parse_url($url);

        return $parts !== false && isset($parts['scheme'], $parts['host']) && $parts['host'] !== '';
    }

    public static function isSecure(string $url): bool
    {
        if (!self::isAbsolute($url)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, self::LOOPBACK_HOSTS, true));
    }
}

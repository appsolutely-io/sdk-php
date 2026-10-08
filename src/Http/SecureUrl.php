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
 * The check is made with parse_url(), while the request goes out through
 * whatever PSR-18 client the integrator installed, so a URL the two could
 * read differently is refused before either reads it: one with user info
 * (`https://a@b`), a backslash (`http://evil.com\@localhost` has the host
 * `localhost` for parse_url() and `evil.com` for a WHATWG parser),
 * whitespace or a control character. A provider has no reason to publish
 * any of them.
 *
 * @internal
 */
final class SecureUrl
{
    private const array LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    public const string UNAMBIGUOUS_RULE = 'no user info, backslash, whitespace or control character';

    public static function isUnambiguous(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }

        // The authority runs from after "//" to the first "/", "?" or "#"
        // (RFC 3986 section 3.2); an "@" there introduces user info.
        $authority = preg_match('~^[^:/?#]*://([^/?#]*)~', $url, $match) === 1 ? $match[1] : '';

        return !str_contains($authority, '@');
    }

    public static function isAbsolute(string $url): bool
    {
        $parts = parse_url($url);

        return $parts !== false && isset($parts['scheme'], $parts['host']) && $parts['host'] !== '';
    }

    public static function isSecure(string $url): bool
    {
        if (!self::isUnambiguous($url) || !self::isAbsolute($url)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, self::LOOPBACK_HOSTS, true));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

use Appsolutely\Sdk\Exception\InvalidConfigException;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Everything the client needs to talk to one Appsolutely deployment.
 *
 * The issuer is kept exactly as given: OpenID Connect compares it to the
 * discovery document and to every ID token's `iss` as a plain string, so a
 * normalised copy would turn a server's trailing-slash mismatch into a silent
 * pass here and a refusal somewhere else.
 *
 * The PSR services are optional; a missing HTTP client or factory is found
 * with php-http/discovery, a missing cache is a per-process memory cache and a
 * missing clock is the system clock.
 */
final readonly class Config
{
    public function __construct(
        public string $issuer,
        public string $apiBaseUrl,
        public string $clientId,
        #[\SensitiveParameter]
        public string $clientSecret,
        public ?ClientInterface $httpClient = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?StreamFactoryInterface $streamFactory = null,
        public ?CacheInterface $cache = null,
        public ?ClockInterface $clock = null,
        public ?LoggerInterface $logger = null,
    ) {
        self::assertBaseUrl('issuer', $issuer);
        self::assertBaseUrl('apiBaseUrl', $apiBaseUrl);

        if ($clientId === '') {
            throw new InvalidConfigException('The client id must not be empty.');
        }

        if ($clientSecret === '') {
            throw new InvalidConfigException('The client secret must not be empty.');
        }
    }

    /**
     * HTTPS only, except on a loopback host, where a developer's local server
     * has no certificate and nothing leaves the machine (RFC 8252 section 8.3).
     */
    private static function assertBaseUrl(string $name, string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw new InvalidConfigException(sprintf('The %s must be an absolute URL, got "%s".', $name, $url));
        }

        if (isset($parts['query']) || isset($parts['fragment']) || str_contains($url, '?') || str_contains($url, '#')) {
            throw new InvalidConfigException(sprintf('The %s must not carry a query or a fragment, got "%s".', $name, $url));
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme === 'https') {
            return;
        }

        if ($scheme === 'http' && self::isLoopback(strtolower($parts['host']))) {
            return;
        }

        throw new InvalidConfigException(sprintf('The %s must use https (plain http is accepted for a loopback host only), got "%s".', $name, $url));
    }

    private static function isLoopback(string $host): bool
    {
        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $host === '127.0.0.1'
            || $host === '[::1]';
    }
}

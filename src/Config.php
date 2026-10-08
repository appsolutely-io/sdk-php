<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Http\SecureUrl;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
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
    /**
     * Seconds of disagreement tolerated between this host's clock and the
     * provider's when checking a token's exp and iat.
     */
    public const int DEFAULT_CLOCK_LEEWAY = 60;

    /** A leeway beyond this would keep an expired ID token usable for minutes. */
    public const int MAX_CLOCK_LEEWAY = 300;

    public function __construct(
        public string $issuer,
        public string $clientId,
        #[\SensitiveParameter]
        public string $clientSecret,
        public ?ClientInterface $httpClient = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?StreamFactoryInterface $streamFactory = null,
        public ?CacheInterface $cache = null,
        public ?ClockInterface $clock = null,
        public ?LoggerInterface $logger = null,
        public ClientAuthentication $clientAuthentication = ClientAuthentication::ClientSecretBasic,
        public int $clockLeeway = self::DEFAULT_CLOCK_LEEWAY,
    ) {
        self::assertIssuer($issuer);

        if ($clientId === '') {
            throw new InvalidConfigException('The client id must not be empty.');
        }

        if ($clientSecret === '') {
            throw new InvalidConfigException('The client secret must not be empty.');
        }

        if ($clockLeeway < 0 || $clockLeeway > self::MAX_CLOCK_LEEWAY) {
            throw new InvalidConfigException(sprintf('The clock leeway must be between 0 and %d seconds, got %d.', self::MAX_CLOCK_LEEWAY, $clockLeeway));
        }
    }

    /**
     * HTTPS, or plain HTTP on a loopback host only (see SecureUrl), and no
     * query or fragment, which would be carried into every derived URL.
     */
    private static function assertIssuer(string $url): void
    {
        if (!SecureUrl::isAbsolute($url)) {
            throw new InvalidConfigException(sprintf('The issuer must be an absolute URL, got "%s".', $url));
        }

        if (str_contains($url, '?') || str_contains($url, '#')) {
            throw new InvalidConfigException(sprintf('The issuer must not carry a query or a fragment, got "%s".', $url));
        }

        if (!SecureUrl::isSecure($url)) {
            throw new InvalidConfigException(sprintf('The issuer must use https (plain http is accepted for localhost, 127.0.0.1 and [::1] only), got "%s".', $url));
        }
    }
}

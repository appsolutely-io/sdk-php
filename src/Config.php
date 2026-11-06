<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Cache\TokenLock;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Http\SecureUrl;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Support\Untrusted;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Everything the client needs to talk to one site: where its API answers
 * and, as the integration needs them, the OpenID issuer its members sign in
 * with, the OAuth client that signs them in, and the site backend's machine
 * client. Every site answers the same paths in the same shapes, so moving
 * between sites changes the base URL, the issuer and the credentials,
 * nothing else.
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

    /**
     * Seconds a request may take, end to end. Above the 20 seconds the site
     * holds a keyed duplicate before answering it, so that wait is never cut
     * short; well below the minutes a worker would otherwise hang.
     */
    public const float DEFAULT_TIMEOUT = 30.0;

    /** Seconds to establish a connection, TLS included. */
    public const float DEFAULT_CONNECT_TIMEOUT = 10.0;

    /** A request timeout beyond this is no timeout a worker can live with. */
    public const float MAX_TIMEOUT = 600.0;

    /**
     * @param string $baseUrl the site's origin, such as `https://shop.example.com`, which every API path is joined to
     * @param string|null $issuer the site's OpenID issuer, exactly as its discovery document names it; needed for
     *                           member sign-in and for the backend client
     * @param string|null $clientId the OAuth client that signs members in, with its secret; leave both out when
     *                              the integration never signs members in (Client::oidc() is then refused)
     * @param string|null $backendClientId the site backend's machine client, registered apart from the one that
     *                                     signs members in; with its secret, Client::backend() calls as it
     * @param list<string> $backendScopes the scopes to ask the backend's token for; none asks for every scope the
     *                                    machine client is registered with
     * @param ClientAuthentication|null $backendClientAuthentication how the backend's client authenticates to the
     *                                                               token endpoint; null for $clientAuthentication
     * @param float $timeout seconds a request may take, applied to the HTTP client the SDK finds for itself
     *                       (Guzzle or Symfony HttpClient); a client passed in keeps its own
     * @param float $connectTimeout seconds to connect, applied the same way (Guzzle only; Symfony's idle
     *                              timeout is the request timeout)
     * @param TokenLock|null $tokenLock a lock shared by the processes that share the cache, so one of them asks
     *                                  for the backend's token when it is due; without one, each asks for its own
     */
    public function __construct(
        public string $baseUrl,
        public ?string $issuer = null,
        public ?string $clientId = null,
        #[\SensitiveParameter]
        private ?string $clientSecret = null,
        public ?ClientInterface $httpClient = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?StreamFactoryInterface $streamFactory = null,
        public ?CacheInterface $cache = null,
        public ?ClockInterface $clock = null,
        public ?LoggerInterface $logger = null,
        public ClientAuthentication $clientAuthentication = ClientAuthentication::ClientSecretBasic,
        public int $clockLeeway = self::DEFAULT_CLOCK_LEEWAY,
        public RetryPolicy $retryPolicy = new RetryPolicy(),
        public ?string $backendClientId = null,
        #[\SensitiveParameter]
        private ?string $backendClientSecret = null,
        public array $backendScopes = [],
        public ?ClientAuthentication $backendClientAuthentication = null,
        public float $timeout = self::DEFAULT_TIMEOUT,
        public float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        public ?TokenLock $tokenLock = null,
    ) {
        self::assertBaseUrl($baseUrl);
        if ($issuer !== null) {
            self::assertIssuer($issuer);
        }

        if ($clientId === '') {
            throw new InvalidConfigException('The client id must not be empty.');
        }

        if ($clientSecret === '') {
            throw new InvalidConfigException('The client secret must not be empty.');
        }

        if (($clientId === null) !== ($clientSecret === null)) {
            throw new InvalidConfigException('The member sign-in client id and secret go together: give both, or neither.');
        }

        if (($backendClientId === null) !== ($backendClientSecret === null)) {
            throw new InvalidConfigException('The backend client id and secret go together: give both, or neither.');
        }

        if ($backendClientId === '' || $backendClientSecret === '') {
            throw new InvalidConfigException('The backend client id and secret must not be empty.');
        }

        if ($issuer === null && ($clientId !== null || $backendClientId !== null)) {
            throw new InvalidConfigException('The issuer is needed to sign members in and to call as the backend: give the site\'s OpenID issuer.');
        }

        foreach ($backendScopes as $scope) {
            // RFC 6749 section 3.3: a scope token is printable ASCII without
            // a space, a double quote or a backslash.
            if (preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/', $scope) !== 1) {
                throw new InvalidConfigException('A backend scope must be a non-empty run of printable ASCII characters without spaces, double quotes or backslashes.');
            }
        }

        if ($clockLeeway < 0 || $clockLeeway > self::MAX_CLOCK_LEEWAY) {
            throw new InvalidConfigException(sprintf('The clock leeway must be between 0 and %d seconds, got %d.', self::MAX_CLOCK_LEEWAY, $clockLeeway));
        }

        if ($timeout <= 0 || $timeout > self::MAX_TIMEOUT) {
            throw new InvalidConfigException(sprintf('The request timeout must be above zero and at most %d seconds, got %s.', (int) self::MAX_TIMEOUT, $timeout));
        }

        if ($connectTimeout <= 0 || $connectTimeout > $timeout) {
            throw new InvalidConfigException(sprintf('The connect timeout must be above zero and at most the request timeout, got %s seconds.', $connectTimeout));
        }
    }

    /**
     * @internal read by the client to authenticate itself; not a way to get the secret back out
     */
    public function clientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * @internal read by the client to authenticate its backend; not a way to get the secret back out
     */
    public function backendClientSecret(): ?string
    {
        return $this->backendClientSecret;
    }

    /**
     * What var_dump() and print_r() show, here and inside every object that
     * holds this Config: the secret is replaced so a debug dump or an error
     * page does not print it. (var_export() ignores this hook; do not export
     * a Config.)
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'issuer' => $this->issuer,
            'clientId' => $this->clientId,
            'clientSecret' => '[redacted]',
            'httpClient' => $this->httpClient,
            'requestFactory' => $this->requestFactory,
            'streamFactory' => $this->streamFactory,
            'cache' => $this->cache,
            'clock' => $this->clock,
            'logger' => $this->logger,
            'clientAuthentication' => $this->clientAuthentication,
            'clockLeeway' => $this->clockLeeway,
            'retryPolicy' => $this->retryPolicy,
            'backendClientId' => $this->backendClientId,
            'backendClientSecret' => $this->backendClientSecret === null ? null : '[redacted]',
            'backendScopes' => $this->backendScopes,
            'backendClientAuthentication' => $this->backendClientAuthentication,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'tokenLock' => $this->tokenLock,
        ];
    }

    /**
     * Refused rather than redacted: a copy without the secret could not
     * authenticate, and one with it would carry the secret into whatever
     * store the serialized string ends up in (a session, a cache, a queue
     * payload). The PSR services it holds do not serialize either. Client and
     * OpenIdClient hold a Config, so they are refused too.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('A Config holds the client secrets and is not serialized; build it again from its settings.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('A Config holds the client secrets and is not unserialized; build it again from its settings.');
    }

    private static function assertIssuer(string $url): void
    {
        self::assertSecureUrl('issuer', $url);
    }

    /**
     * An origin: the API paths the client calls are absolute, so a path
     * here would be dropped or doubled rather than honoured.
     */
    private static function assertBaseUrl(string $url): void
    {
        self::assertSecureUrl('base URL', $url);

        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null && $path !== false && $path !== '' && $path !== '/') {
            throw new InvalidConfigException(sprintf('The base URL must be the site\'s origin, without a path, got "%s".', Untrusted::text($url, Untrusted::MAX_LONG_LENGTH)));
        }
    }

    /**
     * HTTPS, or plain HTTP on a loopback host only, nothing parsers read
     * differently (see SecureUrl), and no query or fragment, which would be
     * carried into every derived URL. The value is echoed as printable text:
     * a refused URL may hold the very control characters refused here.
     */
    private static function assertSecureUrl(string $name, string $url): void
    {
        $shown = Untrusted::text($url, Untrusted::MAX_LONG_LENGTH);

        if (!SecureUrl::isUnambiguous($url)) {
            throw new InvalidConfigException(sprintf('The %s must contain %s, got "%s".', $name, SecureUrl::UNAMBIGUOUS_RULE, $shown));
        }

        if (!SecureUrl::isAbsolute($url)) {
            throw new InvalidConfigException(sprintf('The %s must be an absolute URL, got "%s".', $name, $shown));
        }

        if (str_contains($url, '?') || str_contains($url, '#')) {
            throw new InvalidConfigException(sprintf('The %s must not carry a query or a fragment, got "%s".', $name, $shown));
        }

        if (!SecureUrl::isSecure($url)) {
            throw new InvalidConfigException(sprintf('The %s must use https (plain http is accepted for localhost, 127.0.0.1 and [::1] only), got "%s".', $name, $shown));
        }
    }
}

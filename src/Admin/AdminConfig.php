<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin;

use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Cache\TokenLock;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Support\Untrusted;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * One site's Administrator API, called with an administrator credential: an
 * OAuth client an administrator issued on the site, holding the `admin:*`
 * scopes asked for here. Its access token comes from the issuer's token
 * endpoint by the client-credentials grant.
 *
 * The site, issuer and HTTP settings are held to the same rules as a
 * {@see Config}'s; the secret is never shown and the Config is never
 * serialized.
 */
final readonly class AdminConfig
{
    /** An Administrator API scope: `admin:<resource>:<verb or action>`. */
    private const string SCOPE_PATTERN = '/^admin:[a-z0-9]+(?:-[a-z0-9]+)*:[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** The settings shared with a Config, checked by it. */
    public Config $site;

    /** Held wrapped, so neither a dump nor an export nor an array cast shows it. */
    private \SensitiveParameterValue $clientSecret;

    /**
     * @param list<string> $scopes the Administrator API scopes to ask for, at least one, such as `admin:articles:read`
     */
    public function __construct(
        public string $baseUrl,
        public string $issuer,
        public string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        public array $scopes,
        public ?ClientInterface $httpClient = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?StreamFactoryInterface $streamFactory = null,
        public ?CacheInterface $cache = null,
        public ?ClockInterface $clock = null,
        public ?LoggerInterface $logger = null,
        public ClientAuthentication $clientAuthentication = ClientAuthentication::ClientSecretBasic,
        public RetryPolicy $retryPolicy = new RetryPolicy(),
        public float $timeout = Config::DEFAULT_TIMEOUT,
        public float $connectTimeout = Config::DEFAULT_CONNECT_TIMEOUT,
        public ?TokenLock $tokenLock = null,
    ) {
        if ($clientId === '') {
            throw new InvalidConfigException('The administrator credential\'s client id must not be empty.');
        }

        if ($clientSecret === '') {
            throw new InvalidConfigException('The administrator credential\'s client secret must not be empty.');
        }

        $this->clientSecret = new \SensitiveParameterValue($clientSecret);

        if ($scopes === []) {
            throw new InvalidConfigException('An administrator credential asks for at least one Administrator API scope, such as "admin:articles:read".');
        }

        foreach ($scopes as $scope) {
            if (preg_match(self::SCOPE_PATTERN, $scope) !== 1) {
                throw new InvalidConfigException(sprintf('"%s" is not an Administrator API scope; those read "admin:<resource>:<verb>", such as "admin:articles:read".', Untrusted::text($scope)));
            }
        }

        // Validates the base URL, the issuer and the timeouts as a Config
        // does, and is what the token endpoint is asked through. It holds no
        // secret: the credential is passed to each token request.
        $this->site = new Config(
            baseUrl: $baseUrl,
            issuer: $issuer,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            cache: $cache,
            clock: $clock,
            logger: $logger,
            clientAuthentication: $clientAuthentication,
            retryPolicy: $retryPolicy,
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            tokenLock: $tokenLock,
        );
    }

    public function clientSecret(): string
    {
        $secret = $this->clientSecret->getValue();
        assert(is_string($secret));

        return $secret;
    }

    /**
     * What print_r(), var_dump() and debuggers show, here and in anything that
     * holds this Config: the secret is replaced so a debug dump or an error
     * page does not print it.
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
            'scopes' => $this->scopes,
            'clientAuthentication' => $this->clientAuthentication,
            'retryPolicy' => $this->retryPolicy,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'tokenLock' => $this->tokenLock,
        ];
    }

    /**
     * Refused rather than redacted, as a Config is: a copy without the secret
     * could not authenticate, and one with it would carry the secret into
     * whatever store the serialized string ends up in.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('An AdminConfig holds the administrator credential\'s secret and is not serialized; build it again from its settings.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('An AdminConfig holds the administrator credential\'s secret and is not unserialized; build it again from its settings.');
    }
}

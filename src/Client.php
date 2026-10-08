<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Clock\SystemClock;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Oidc\OpenIdClient;
use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Log\NullLogger;

/**
 * The entry point: one Config in, every part of the API out.
 */
final readonly class Client
{
    private OpenIdClient $oidc;

    public function __construct(Config $config)
    {
        try {
            $http = new HttpTransport(
                $config->httpClient ?? Psr18ClientDiscovery::find(),
                $config->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
                $config->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            );
        } catch (NotFoundException $exception) {
            throw new InvalidConfigException(
                'No PSR-18 HTTP client or PSR-17 factory was passed in or found installed; require one, such as guzzlehttp/guzzle or symfony/http-client with nyholm/psr7.',
                0,
                $exception,
            );
        }

        $clock = $config->clock ?? new SystemClock();

        $this->oidc = new OpenIdClient(
            $config,
            $http,
            $config->cache ?? new InMemoryCache($clock),
            $clock,
            $config->logger ?? new NullLogger(),
        );
    }

    /**
     * Refused like Config's, whose secret this holds.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('A Client holds the client secret and is not serialized; build it again from its Config.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('A Client holds the client secret and is not unserialized; build it again from its Config.');
    }

    public function oidc(): OpenIdClient
    {
        return $this->oidc;
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

use Appsolutely\Sdk\Api\BackendApi;
use Appsolutely\Sdk\Api\BackendToken;
use Appsolutely\Sdk\Api\PublicApi;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Clock\SystemClock;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Oidc\OpenIdClient;
use Psr\Log\NullLogger;

/**
 * The entry point: one Config in, every part of the site's API out: the
 * operations anyone may call, the site's backend, and forMember() for a
 * signed-in member. A site's administrators call the Administrator API,
 * through Admin\AdminClient.
 */
final readonly class Client
{
    private ?OpenIdClient $oidc;

    private bool $signsMembersIn;

    private SiteApi $site;

    private PublicApi $api;

    private ?BackendApi $backend;

    public function __construct(Config $config)
    {
        $clock = $config->clock ?? new SystemClock();
        $logger = $config->logger ?? new NullLogger();

        $http = HttpTransport::forConfig($config, $logger);

        $cache = $config->cache;
        if ($cache === null) {
            $cache = new InMemoryCache($clock);
            $logger->warning('No PSR-16 cache was passed in Config, so a memory cache that lives as long as this process is used: the discovery document, the signing keys and the backend token are fetched again in every process (under PHP-FPM, every request). Pass your application\'s shared cache.');
        }

        // Built whenever there is an issuer: the backend asks its token
        // endpoint too. Handed out by oidc() only with a member client.
        $issuer = $config->issuer;
        $this->oidc = $issuer === null ? null : new OpenIdClient(
            $config,
            $http,
            $cache,
            $clock,
            $logger,
        );
        $this->site = new SiteApi($http, $config->baseUrl, null, $clock, $config->retryPolicy, $logger);
        $this->api = new PublicApi($this->site);
        $this->signsMembersIn = $config->clientId !== null;

        $backendSecret = $config->backendClientSecret();
        $this->backend = $config->backendClientId === null || $backendSecret === null || $this->oidc === null || $issuer === null ? null : new BackendApi($this->site->withBackendToken(new BackendToken(
            $this->oidc,
            $cache,
            $clock,
            $issuer,
            $config->backendClientId,
            $backendSecret,
            $config->backendScopes,
            $config->backendClientAuthentication,
            $config->tokenLock,
            $logger,
        )));
    }

    /**
     * Refused like Config's, whose secret this holds.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('A Client holds the client secrets and is not serialized; build it again from its Config.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('A Client holds the client secrets and is not unserialized; build it again from its Config.');
    }

    /**
     * Signing members in; the Config must hold the issuer and the member
     * sign-in client's id and secret.
     */
    public function oidc(): OpenIdClient
    {
        if (!$this->signsMembersIn || $this->oidc === null) {
            throw new InvalidConfigException('The Config holds no member sign-in client (issuer, client id and client secret), so there is no member sign-in to use.');
        }

        return $this->oidc;
    }

    /**
     * The Site API operations anyone may call, sent without a credential.
     */
    public function api(): PublicApi
    {
        return $this->api;
    }

    /**
     * The Site API as the site's own backend, with a token its machine client
     * is granted; the Config must hold that client's id and secret.
     */
    public function backend(): BackendApi
    {
        return $this->backend ?? throw new InvalidConfigException('The Config holds no backend client id and secret, so there is no backend to call as.');
    }

    /**
     * The client acting as a signed-in member, with the access token the
     * member's sign-in (or its latest refresh) returned.
     */
    public function forMember(#[\SensitiveParameter] string $accessToken): MemberClient
    {
        return new MemberClient($this->site->withToken($accessToken));
    }
}

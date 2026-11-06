<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin;

use Appsolutely\Sdk\Admin\Resource\Articles;
use Appsolutely\Sdk\Admin\Resource\FormEntries;
use Appsolutely\Sdk\Admin\Resource\Orders;
use Appsolutely\Sdk\Admin\Resource\Pages;
use Appsolutely\Sdk\Admin\Resource\Products;
use Appsolutely\Sdk\Admin\Resource\WebhookDeliveries;
use Appsolutely\Sdk\Api\BackendToken;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Clock\SystemClock;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Oidc\OpenIdClient;
use Psr\Log\NullLogger;

/**
 * A client for one site's Administrator API (`/api/admin/v1`), acting with
 * an administrator credential.
 *
 * The credential's access token is asked for at the issuer's token endpoint
 * when first needed, kept in the AdminConfig's cache until shortly before it
 * expires, and asked for again once when the site answers `invalid-token`.
 * It is kept apart from the site backend's token in the same cache. A scope
 * the credential lacks is answered `403` `insufficient-scope` and thrown as a
 * ForbiddenException: neither a new token nor another attempt would change
 * it.
 */
final readonly class AdminClient
{
    /** The Administrator API's version path; every call is under it. */
    public const string BASE_PATH = '/api/admin/v1/';

    /** Where the credential's token is kept in the cache. */
    public const string TOKEN_KEY_PREFIX = 'appsolutely.admin.token.';

    private SiteApi $api;

    private AdminCaller $caller;

    public function __construct(AdminConfig $config)
    {
        $site = $config->site;
        $clock = $site->clock ?? new SystemClock();
        $logger = $site->logger ?? new NullLogger();
        $http = HttpTransport::forConfig($site, $logger);

        $cache = $site->cache;
        if ($cache === null) {
            $cache = new InMemoryCache($clock);
            $logger->warning('No PSR-16 cache was passed in AdminConfig, so a memory cache that lives as long as this process is used: the administrator credential\'s token is asked for again in every process (under PHP-FPM, every request). Pass your application\'s shared cache.');
        }

        $token = new BackendToken(
            new OpenIdClient($site, $http, $cache, $clock, $logger),
            $cache,
            $clock,
            $config->issuer,
            $config->clientId,
            $config->clientSecret(),
            $config->scopes,
            $config->clientAuthentication,
            $site->tokenLock,
            $logger,
            null,
            self::TOKEN_KEY_PREFIX,
        );

        $this->api = (new SiteApi($http, $config->baseUrl, null, $clock, $site->retryPolicy, $logger))
            ->withBackendToken($token, self::BASE_PATH);
        $this->caller = new AdminCaller($this->api);
    }

    public function articles(): Articles
    {
        return new Articles($this->caller);
    }

    public function pages(): Pages
    {
        return new Pages($this->caller);
    }

    public function products(): Products
    {
        return new Products($this->caller);
    }

    public function orders(): Orders
    {
        return new Orders($this->caller);
    }

    public function formEntries(): FormEntries
    {
        return new FormEntries($this->caller);
    }

    public function webhookDeliveries(): WebhookDeliveries
    {
        return new WebhookDeliveries($this->caller);
    }

    /**
     * The untyped calls, with the credential's token, retries and answers,
     * on paths under `/api/admin/v1/` only: a path outside it is refused
     * before anything is sent.
     */
    public function raw(): SiteApi
    {
        return $this->api;
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('An AdminClient holds the administrator credential\'s secret and is not serialized; build it again from its AdminConfig.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('An AdminClient holds the administrator credential\'s secret and is not unserialized; build it again from its AdminConfig.');
    }
}

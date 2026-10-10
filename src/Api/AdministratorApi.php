<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Model\ApiVersion;
use Appsolutely\Sdk\Resource\AccountStates;
use Appsolutely\Sdk\Resource\Articles;
use Appsolutely\Sdk\Resource\FormEntries;
use Appsolutely\Sdk\Resource\MagicLink;
use Appsolutely\Sdk\Resource\Orders;
use Appsolutely\Sdk\Resource\Pages;
use Appsolutely\Sdk\Resource\Products;
use Appsolutely\Sdk\Resource\Sync;
use Appsolutely\Sdk\Resource\WebhookDeliveries;

/**
 * The Site API as the site's administrator, from Client::api(): a typed
 * method for every administrator operation of the document, grouped by
 * resource, and the operations that need no credential, which are sent
 * without one.
 */
final readonly class AdministratorApi
{
    private Caller $administrator;

    private Caller $anyone;

    /**
     * @internal obtain it from Client::api()
     */
    public function __construct(private SiteApi $api)
    {
        $this->administrator = new Caller($api, Audience::Administrator);
        $this->anyone = new Caller($api->withoutToken(), Audience::Anyone);
    }

    /**
     * The untyped calls, with the same credential, retries and answers: for
     * an operation a site has that this client was not written against.
     */
    public function raw(): SiteApi
    {
        return $this->api;
    }

    /**
     * The lifecycle of the API version the site answers.
     */
    #[Endpoint(Operation::GetApiVersion)]
    public function version(): ApiVersion
    {
        return $this->anyone->read(Operation::GetApiVersion, ApiVersion::SCHEMA, ApiVersion::from(...));
    }

    /**
     * The OpenAPI document the site serves for this version, as decoded JSON:
     * a member named like a decimal integer, such as a response's `200`,
     * has an integer key.
     *
     * @return array<array-key, mixed>
     */
    #[Endpoint(Operation::GetOpenApiDocument)]
    public function openApiDocument(): array
    {
        return $this->anyone->object(Operation::GetOpenApiDocument);
    }

    public function magicLink(): MagicLink
    {
        return new MagicLink($this->anyone);
    }

    public function pages(): Pages
    {
        return new Pages($this->administrator);
    }

    public function articles(): Articles
    {
        return new Articles($this->administrator);
    }

    public function products(): Products
    {
        return new Products($this->administrator);
    }

    public function orders(): Orders
    {
        return new Orders($this->administrator);
    }

    public function formEntries(): FormEntries
    {
        return new FormEntries($this->administrator);
    }

    public function accountStates(): AccountStates
    {
        return new AccountStates($this->administrator);
    }

    public function webhookDeliveries(): WebhookDeliveries
    {
        return new WebhookDeliveries($this->administrator);
    }

    public function sync(): Sync
    {
        return new Sync($this->administrator);
    }
}

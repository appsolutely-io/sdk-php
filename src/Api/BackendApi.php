<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Model\BackendClient;
use Appsolutely\Sdk\Resource\Backend\AccountStates;
use Appsolutely\Sdk\Resource\Backend\CheckoutSessions;
use Appsolutely\Sdk\Resource\Backend\Deliveries;
use Appsolutely\Sdk\Resource\Backend\FeatureKeys;
use Appsolutely\Sdk\Resource\Backend\FeatureValues;
use Appsolutely\Sdk\Resource\Backend\Fulfilments;
use Appsolutely\Sdk\Resource\Backend\PortalSessions;
use Appsolutely\Sdk\Resource\Backend\Products;
use Appsolutely\Sdk\Resource\Backend\ReturnTargets;
use Appsolutely\Sdk\Resource\Backend\RoleAssignments;
use Appsolutely\Sdk\Resource\Backend\Roles;
use Appsolutely\Sdk\Resource\Backend\Seats;
use Appsolutely\Sdk\Resource\Backend\StorePurchases;
use Appsolutely\Sdk\Resource\Backend\TestClocks;
use Appsolutely\Sdk\Resource\Backend\WebhookEndpoints;

/**
 * The Site API as the site's own backend: the calls answered for the token
 * its machine client is granted, grouped by resource.
 */
final readonly class BackendApi
{
    private Caller $backend;

    /**
     * @internal obtain it from Client::backend()
     */
    public function __construct(private SiteApi $api)
    {
        $this->backend = new Caller($api, Audience::Backend);
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
     * The machine client the token was granted to: its mode, the site it
     * belongs to and its scopes.
     */
    #[Endpoint(Operation::GetClient)]
    public function client(): BackendClient
    {
        return $this->backend->read(Operation::GetClient, BackendClient::class);
    }

    public function accountStates(): AccountStates
    {
        return new AccountStates($this->backend);
    }

    /**
     * The site's product catalogue, read with the `catalogue:read` scope.
     */
    public function products(): Products
    {
        return new Products($this->backend);
    }

    /**
     * Digital deliveries the backend fulfils, with the `fulfilments:write` scope.
     */
    public function fulfilments(): Fulfilments
    {
        return new Fulfilments($this->backend);
    }

    public function webhookEndpoints(): WebhookEndpoints
    {
        return new WebhookEndpoints($this->backend);
    }

    public function deliveries(): Deliveries
    {
        return new Deliveries($this->backend);
    }

    public function checkoutSessions(): CheckoutSessions
    {
        return new CheckoutSessions($this->backend);
    }

    public function portalSessions(): PortalSessions
    {
        return new PortalSessions($this->backend);
    }

    public function storePurchases(): StorePurchases
    {
        return new StorePurchases($this->backend);
    }

    public function testClocks(): TestClocks
    {
        return new TestClocks($this->backend);
    }

    public function returnTargets(): ReturnTargets
    {
        return new ReturnTargets($this->backend);
    }

    public function featureKeys(): FeatureKeys
    {
        return new FeatureKeys($this->backend);
    }

    public function featureValues(): FeatureValues
    {
        return new FeatureValues($this->backend);
    }

    public function roles(): Roles
    {
        return new Roles($this->backend);
    }

    public function roleAssignments(): RoleAssignments
    {
        return new RoleAssignments($this->backend);
    }

    public function seats(): Seats
    {
        return new Seats($this->backend);
    }
    public function organisationRoles(): \Appsolutely\Sdk\Resource\Backend\OrganisationRoles
    {
        return new \Appsolutely\Sdk\Resource\Backend\OrganisationRoles($this->backend);
    }
}

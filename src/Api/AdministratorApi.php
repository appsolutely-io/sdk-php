<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Model\ApiVersion;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Resource\MagicLink;

/**
 * The Site API as the site's administrator, from Client::api(): a typed
 * method for every administrator operation of the document, grouped by
 * resource, and the operations that need no credential, which are sent
 * without one.
 */
final readonly class AdministratorApi
{
    private Caller $anyone;

    /**
     * @internal obtain it from Client::api()
     */
    public function __construct(private SiteApi $api)
    {
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
        return ApiVersion::from(Fields::of($this->anyone->object(Operation::GetApiVersion), ApiVersion::SCHEMA));
    }

    /**
     * The OpenAPI document the site serves for this version, as decoded JSON.
     *
     * @return array<string, mixed>
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
}

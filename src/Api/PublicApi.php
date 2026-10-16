<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Model\ApiVersion;
use Appsolutely\Sdk\Resource\MagicLink;

/**
 * The Site API operations anyone may call, from Client::api(): the version
 * record, the API document and the magic-link sign-in, sent without a
 * credential. A site's administrators call the Administrator API
 * (Admin\AdminClient); its own backend calls Client::backend().
 */
final readonly class PublicApi
{
    private Caller $anyone;

    /**
     * @internal obtain it from Client::api()
     */
    public function __construct(private SiteApi $api)
    {
        $this->anyone = new Caller($api, Audience::Anyone);
    }

    /**
     * The untyped calls, without a credential, with the same retries and
     * answers: for an operation a site has that this client was not
     * written against.
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
        return $this->anyone->read(Operation::GetApiVersion, ApiVersion::class);
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
}

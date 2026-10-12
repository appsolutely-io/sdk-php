<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

/**
 * The Site API as the site's own backend: the calls answered for the token
 * its machine client is granted, grouped by resource.
 */
final readonly class BackendApi
{
    /**
     * @internal obtain it from Client::backend()
     */
    public function __construct(private SiteApi $api) {}

    /**
     * The untyped calls, with the same credential, retries and answers: for
     * an operation a site has that this client was not written against.
     */
    public function raw(): SiteApi
    {
        return $this->api;
    }
}

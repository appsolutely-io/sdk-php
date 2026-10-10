<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

/**
 * The Site API as one signed-in member, from Client::forMember()->api(): a
 * typed method for every member operation of the document, each carrying
 * the member's access token.
 */
final readonly class MemberApi
{
    /**
     * @internal obtain it from Client::forMember()->api()
     */
    public function __construct(private SiteApi $api) {}

    /**
     * The untyped calls, with the member's token, retries and answers: for
     * an operation a site has that this client was not written against.
     */
    public function raw(): SiteApi
    {
        return $this->api;
    }
}

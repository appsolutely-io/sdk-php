<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

/**
 * Whose credential an operation of the Site API is called with.
 */
enum Audience
{
    /** No credential: the version record, the API document and the magic-link sign-in. */
    case Anyone;

    /** The site's administrator token, from Client::api(). */
    case Administrator;

    /** A signed-in member's access token, from Client::forMember()->api(). */
    case Member;
}

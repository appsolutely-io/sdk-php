<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

/**
 * Whose credential an operation of the Site API is called with.
 */
enum Audience
{
    /** No credential: the version record, the API document and the magic-link sign-in, from Client::api(). */
    case Anyone;

    /** A signed-in member's access token, from Client::forMember()->api(). */
    case Member;

    /** The token the site backend's machine client is granted, from Client::backend(). */
    case Backend;

    /**
     * Either a member's access token or the backend's token, for reference
     * data both read alike, from Client::forMember()->api() and Client::backend().
     */
    case MemberOrBackend;

    /**
     * Whether a caller holding this audience's credential may call an
     * operation for the given one.
     */
    public function reaches(self $operation): bool
    {
        return $operation === $this || ($operation === self::MemberOrBackend && ($this === self::Member || $this === self::Backend));
    }
}

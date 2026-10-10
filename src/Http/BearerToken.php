<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

/**
 * @internal
 */
final class BearerToken
{
    /**
     * Whether a credential can be sent in `Authorization: Bearer` as it is.
     * The site's tokens are not all RFC 6750 b64token (an administrator token
     * holds a `|`), so any visible ASCII is accepted; a space, a line break or
     * a control character is not, since it could end the header and add one
     * of its own.
     */
    public static function isSendable(#[\SensitiveParameter] string $token): bool
    {
        return preg_match('/^[\x21-\x7E]+$/D', $token) === 1;
    }
}

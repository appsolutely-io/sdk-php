<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use RuntimeException;

/**
 * A server answered a call this client made, but not with something it can
 * use: an OpenID provider's status without an OAuth error body, or a Site
 * API answer that breaks the site's API document (a body that is not JSON,
 * a required field missing, a field of another type, a list whose cursors
 * lead back to a page already given). The message names the schema and the
 * field, never the value.
 *
 * It is thrown where the call was made. A webhook delivery whose `data`
 * breaks its shape is an UnexpectedPayloadException instead: that one
 * arrives at an endpoint, which should answer it with a 5xx.
 */
final class UnexpectedResponseException extends RuntimeException implements AppsolutelyException
{
    /**
     * @param int $statusCode the HTTP status of the answer; 0 when the fault is a field of a successful answer's body
     * @param string|null $schema the name of the document's schema the answer was read as, when a field broke it
     */
    public function __construct(string $message, public readonly int $statusCode, public readonly ?string $schema = null)
    {
        parent::__construct($message);
    }
}

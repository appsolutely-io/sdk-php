<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use RuntimeException;

/**
 * The server answered, but not with something this client can use: a status
 * without an OAuth error body, or a body that breaks the protocol.
 */
final class UnexpectedResponseException extends RuntimeException implements AppsolutelyException
{
    public function __construct(string $message, public readonly int $statusCode)
    {
        parent::__construct($message);
    }
}

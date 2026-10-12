<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use RuntimeException;

/**
 * The request never produced a response: the network failed (DNS,
 * connection, TLS or timeout), or the HTTP client refused the request
 * before sending it. The HTTP client's own exception is the previous one.
 */
final class TransportException extends RuntimeException implements AppsolutelyException
{
    /**
     * Whether the network failed (PSR-18's NetworkExceptionInterface), the
     * one case a retry can change; a request the client refused fails the
     * same way every time.
     */
    public function isNetworkFailure(): bool
    {
        return $this->getPrevious() instanceof NetworkExceptionInterface;
    }
}

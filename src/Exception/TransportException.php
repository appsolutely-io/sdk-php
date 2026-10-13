<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
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
     * Whether the network failed, the one case a retry can change; a
     * request the client refused fails the same way every time.
     *
     * The network failed when the HTTP client says so (PSR-18's
     * NetworkExceptionInterface), and when Guzzle's cURL transfer failed
     * before any answer arrived: Guzzle reports a connection lost after the
     * request went out (cURL errors 55 and 56, say) as a RequestException
     * without a response, with the cURL error in its handler context. One
     * Guzzle refused before sending carries no cURL error. Guzzle need not
     * be installed: without it the instanceof is simply false.
     */
    public function isNetworkFailure(): bool
    {
        $previous = $this->getPrevious();
        if ($previous instanceof NetworkExceptionInterface) {
            return true;
        }

        return $previous instanceof GuzzleRequestException
            && $previous->getResponse() === null
            && is_int($previous->getHandlerContext()['errno'] ?? null)
            && $previous->getHandlerContext()['errno'] !== 0;
    }
}

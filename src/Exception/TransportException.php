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
     * The cURL errors that mean the connection broke while the request was
     * in transit, which Guzzle reports as a RequestException rather than a
     * ConnectException: a failure sending (55), a failure receiving (56)
     * and an HTTP/2 stream reset (92). Every other cURL error it reports
     * that way (a certificate, a CA file, a proxy, a protocol, a URL) fails
     * the same way on the next attempt.
     */
    private const array CURL_IN_TRANSIT = [55, 56, 92];

    /**
     * Whether the network failed, the one case a retry can change; a
     * request the client refused, or one that fails the same way each
     * time, is not.
     *
     * The network failed when the HTTP client says so (PSR-18's
     * NetworkExceptionInterface), and when Guzzle's cURL transfer broke in
     * transit before any answer arrived: Guzzle reports that as a
     * RequestException without a response, with the cURL error in its
     * handler context. Guzzle need not be installed: without it the
     * instanceof is simply false.
     */
    public function isNetworkFailure(): bool
    {
        $previous = $this->getPrevious();
        if ($previous instanceof NetworkExceptionInterface) {
            return true;
        }

        return $previous instanceof GuzzleRequestException
            && $previous->getResponse() === null
            && in_array($previous->getHandlerContext()['errno'] ?? null, self::CURL_IN_TRANSIT, true);
    }
}

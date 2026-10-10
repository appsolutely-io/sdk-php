<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use Appsolutely\Sdk\Exception\TransportException;
use Appsolutely\Sdk\Sdk;
use Appsolutely\Sdk\Support\Untrusted;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @internal
 */
final readonly class HttpTransport
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
    ) {}

    public static function isSuccessful(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, #[\SensitiveParameter] array $headers = []): ResponseInterface
    {
        return $this->send($this->request('GET', $url, $headers));
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $headers
     */
    public function postForm(string $url, #[\SensitiveParameter] array $fields, #[\SensitiveParameter] array $headers = []): ResponseInterface
    {
        $request = $this->request('POST', $url, $headers)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streams->createStream(http_build_query($fields, '', '&', PHP_QUERY_RFC1738)));

        return $this->send($request);
    }

    /**
     * Any method, with a JSON body when one is given.
     *
     * @param array<string, string> $headers
     */
    public function json(string $method, string $url, #[\SensitiveParameter] array $headers = [], ?string $body = null): ResponseInterface
    {
        $request = $this->request($method, $url, $headers);
        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streams->createStream($body));
        }

        return $this->send($request);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, string $url, #[\SensitiveParameter] array $headers): RequestInterface
    {
        $request = $this->requests->createRequest($method, $url)
            ->withHeader('User-Agent', Sdk::userAgent())
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * The URL may be one a discovery document named and the message is the
     * HTTP client's, so both reach the message only as short printable text;
     * the client's exception is kept whole as the previous one.
     */
    private function send(#[\SensitiveParameter] RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException(
                sprintf(
                    '%s %s failed: %s',
                    $request->getMethod(),
                    Untrusted::text((string) $request->getUri(), Untrusted::MAX_LONG_LENGTH),
                    Untrusted::text($exception->getMessage(), Untrusted::MAX_LONG_LENGTH),
                ),
                0,
                $exception,
            );
        }
    }
}

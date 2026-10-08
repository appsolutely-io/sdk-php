<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use Appsolutely\Sdk\Exception\TransportException;
use Appsolutely\Sdk\Sdk;
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

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        return $this->send($this->request('GET', $url, $headers));
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $headers
     */
    public function postForm(string $url, array $fields, array $headers = []): ResponseInterface
    {
        $request = $this->request('POST', $url, $headers)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streams->createStream(http_build_query($fields, '', '&', PHP_QUERY_RFC1738)));

        return $this->send($request);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, string $url, array $headers): RequestInterface
    {
        $request = $this->requests->createRequest($method, $url)
            ->withHeader('User-Agent', Sdk::userAgent())
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException(
                sprintf('%s %s failed: %s', $request->getMethod(), (string) $request->getUri(), $exception->getMessage()),
                0,
                $exception,
            );
        }
    }
}

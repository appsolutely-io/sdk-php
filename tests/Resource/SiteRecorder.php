<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A FakeProvider whose site answers each request with the next queued
 * answer, for the resource tests that check what goes over the wire.
 */
final class SiteRecorder
{
    public readonly FakeProvider $provider;

    /** @var list<\Closure(FakeProvider): ResponseInterface> */
    private array $answers = [];

    public function __construct()
    {
        $this->provider = new FakeProvider();
        $this->provider->site = function (RequestInterface $request): ResponseInterface {
            $answer = array_shift($this->answers);
            if ($answer === null) {
                throw new \LogicException('No answer queued for ' . $request->getMethod() . ' ' . $request->getUri());
            }

            return $answer($this->provider);
        };
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function json(array $body, int $status = 200, array $headers = []): self
    {
        $this->answers[] = static fn(FakeProvider $provider): ResponseInterface => $provider->json($body, $status, $headers);

        return $this;
    }

    public function empty(int $status): self
    {
        $this->answers[] = static fn(FakeProvider $provider): ResponseInterface => $provider->factory->createResponse($status);

        return $this;
    }

    /**
     * @param array<string, mixed> $extensions
     */
    public function problem(int $status, string $slug, array $extensions = []): self
    {
        $this->answers[] = static fn(FakeProvider $provider): ResponseInterface => $provider->problem($status, $slug, $extensions);

        return $this;
    }

    public function request(int $index = 0): RequestInterface
    {
        $requests = $this->provider->siteRequests();
        if (!isset($requests[$index])) {
            throw new \LogicException(sprintf('Only %d requests reached the site.', count($requests)));
        }

        return $requests[$index];
    }

    /**
     * The method and the URL after the origin: `GET /api/v1/articles?limit=25`.
     */
    public function line(int $index = 0): string
    {
        $request = $this->request($index);

        return $request->getMethod() . ' ' . substr((string) $request->getUri(), strlen(FakeProvider::BASE_URL));
    }

    /**
     * @return array<mixed>|null
     */
    public function body(int $index = 0): ?array
    {
        $body = (string) $this->request($index)->getBody();

        return $body === '' ? null : (array) json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}

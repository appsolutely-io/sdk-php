<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Http\CacheControl;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Http\Untrusted;
use Psr\SimpleCache\CacheInterface;

/**
 * @internal
 */
final readonly class Discovery
{
    public const int FALLBACK_TTL = 3600;

    public function __construct(
        private HttpTransport $http,
        private CacheInterface $cache,
        private string $issuer,
    ) {}

    public function metadata(): ProviderMetadata
    {
        $key = 'appsolutely.oidc.discovery.' . hash('sha256', $this->issuer);

        $cached = $this->cache->get($key);
        if (is_string($cached) && ($document = Json::decodeObject($cached)) !== null) {
            return $this->validated($document);
        }

        // OpenID Connect Discovery 1.0 section 4: "any terminating / MUST be
        // removed before appending /.well-known/openid-configuration".
        $url = rtrim($this->issuer, '/') . '/.well-known/openid-configuration';
        $response = $this->http->get($url);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new DiscoveryException(sprintf('GET %s answered %d.', $url, $status));
        }

        $body = (string) $response->getBody();
        $document = Json::decodeObject($body)
            ?? throw new DiscoveryException(sprintf('GET %s did not answer a JSON object.', $url));

        $metadata = $this->validated($document);

        $ttl = CacheControl::ttl($response, self::FALLBACK_TTL);
        if ($ttl > 0) {
            $this->cache->set($key, $body, $ttl);
        }

        return $metadata;
    }

    /**
     * OpenID Connect Discovery section 4.3: the issuer in the document must be
     * identical to the one the client was configured with, or a document
     * served from one host could speak for another.
     *
     * @param array<string, mixed> $document
     */
    private function validated(array $document): ProviderMetadata
    {
        $metadata = ProviderMetadata::fromArray($document);

        if ($metadata->issuer !== $this->issuer) {
            throw new DiscoveryException(sprintf(
                'The discovery document names issuer "%s", but the client is configured for "%s".',
                Untrusted::text($metadata->issuer, 200),
                $this->issuer,
            ));
        }

        return $metadata;
    }
}

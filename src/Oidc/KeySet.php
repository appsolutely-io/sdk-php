<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Http\CacheControl;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Http\Untrusted;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * The provider's published signing keys, kept in the PSR-16 cache.
 *
 * firebase/php-jwt's own CachedKeySet is not used because it needs a PSR-6
 * pool, and one cache interface is enough for an integrator to supply.
 *
 * A token naming a key that is not in the cached set may have been signed by
 * a key rotated in since: the set is fetched again, but at most once a minute,
 * so a stream of tokens with made-up key ids cannot turn into a stream of
 * requests to the provider.
 *
 * @internal
 */
final readonly class KeySet
{
    public const int REFETCH_INTERVAL = 60;

    public function __construct(
        private HttpTransport $http,
        private CacheInterface $cache,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private string $jwksUri,
    ) {}

    /**
     * @param list<string> $algorithms
     */
    public function key(string $kid, array $algorithms): Key
    {
        $set = $this->cached() ?? $this->fetch();
        $key = self::find($set['keys'], $kid, $algorithms);
        if ($key !== null) {
            return $key;
        }

        if ($this->now() - $set['fetched_at'] >= self::REFETCH_INTERVAL) {
            $this->logger->info('An ID token names an unknown signing key; fetching the key set again.', ['kid' => Untrusted::text($kid), 'jwks_uri' => $this->jwksUri]);
            $key = self::find($this->fetch()['keys'], $kid, $algorithms);
            if ($key !== null) {
                return $key;
            }
        }

        throw new IdTokenException(sprintf('The ID token is signed with key "%s", which the provider does not publish for %s.', Untrusted::text($kid), implode(' or ', $algorithms)));
    }

    /**
     * @return array{keys: list<array<string, mixed>>, fetched_at: int}|null
     */
    private function cached(): ?array
    {
        $entry = $this->cache->get($this->cacheKey());
        if (!is_array($entry) || !is_string($entry['body'] ?? null) || !is_int($entry['fetched_at'] ?? null)) {
            return null;
        }

        $keys = self::keys($entry['body']);

        return $keys === null ? null : ['keys' => $keys, 'fetched_at' => $entry['fetched_at']];
    }

    /**
     * @return array{keys: list<array<string, mixed>>, fetched_at: int}
     */
    private function fetch(): array
    {
        $response = $this->http->get($this->jwksUri);
        $status = $response->getStatusCode();
        if (!HttpTransport::isSuccessful($response)) {
            throw new DiscoveryException(sprintf('GET %s answered %d.', $this->jwksUri, $status));
        }

        $body = (string) $response->getBody();
        $keys = self::keys($body) ?? throw new DiscoveryException(sprintf('GET %s did not answer a JWK set.', $this->jwksUri));
        $now = $this->now();

        $ttl = CacheControl::ttl($response, Discovery::FALLBACK_TTL);
        if ($ttl > 0) {
            $this->cache->set($this->cacheKey(), ['body' => $body, 'fetched_at' => $now], $ttl);
        }

        return ['keys' => $keys, 'fetched_at' => $now];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private static function keys(string $body): ?array
    {
        $document = Json::decodeObject($body);
        $keys = $document['keys'] ?? null;
        if (!is_array($keys) || !array_is_list($keys)) {
            return null;
        }

        $objects = [];
        foreach ($keys as $key) {
            if (is_array($key)) {
                $objects[] = Json::stringKeys($key);
            }
        }

        return $objects;
    }

    /**
     * RFC 7517 section 4: a key meant for encryption (`use` = `enc`) is never
     * a signing key; a key without `alg` is taken for the algorithm its type
     * implies, which is unambiguous for RSA and for the P-256 curve.
     *
     * @param list<array<string, mixed>> $keys
     * @param list<string> $algorithms
     */
    private static function find(array $keys, string $kid, array $algorithms): ?Key
    {
        foreach ($keys as $jwk) {
            if (($jwk['kid'] ?? null) !== $kid) {
                continue;
            }
            if (isset($jwk['use']) && $jwk['use'] !== 'sig') {
                continue;
            }

            $alg = $jwk['alg'] ?? match (true) {
                ($jwk['kty'] ?? null) === 'RSA' => 'RS256',
                ($jwk['kty'] ?? null) === 'EC' && ($jwk['crv'] ?? null) === 'P-256' => 'ES256',
                default => null,
            };
            if (!is_string($alg) || !in_array($alg, $algorithms, true)) {
                continue;
            }

            try {
                $key = JWK::parseKey($jwk, $alg);
            } catch (\UnexpectedValueException|\InvalidArgumentException|\DomainException) {
                continue;
            }

            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }

    private function cacheKey(): string
    {
        return 'appsolutely.oidc.jwks.' . hash('sha256', $this->jwksUri);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}

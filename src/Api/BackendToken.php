<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Oidc\OpenIdClient;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * The access token the site backend's machine client is granted, asked for
 * when first needed and kept in the cache the application shares between
 * requests until shortly before it expires, so a backend under PHP-FPM asks
 * once per token rather than once per request.
 *
 * @internal built by Client for Client::backend()
 */
final readonly class BackendToken
{
    /**
     * Seconds before its expiry a token is no longer handed out: a call
     * started on a token about to expire would reach the site after it did.
     */
    public const int EXPIRY_MARGIN = 60;

    private const string KEY_PREFIX = 'appsolutely.backend.token.';

    private string $key;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private OpenIdClient $oidc,
        private CacheInterface $cache,
        private ClockInterface $clock,
        string $issuer,
        private string $clientId,
        #[\SensitiveParameter]
        private string $clientSecret,
        private array $scopes,
    ) {
        // Lengths first, so no two (issuer, client, scopes) join to one string.
        $scope = implode(' ', $scopes);
        $this->key = self::KEY_PREFIX . hash('sha256', strlen($issuer) . ':' . $issuer . strlen($clientId) . ':' . $clientId . $scope);
    }

    /**
     * A token to send now: the one kept, or a new one.
     */
    public function token(): string
    {
        $kept = $this->cache->get($this->key);
        if (is_array($kept) && is_string($kept['token'] ?? null) && is_int($kept['usable_until'] ?? null) && $kept['usable_until'] > $this->clock->now()->getTimestamp()) {
            return $kept['token'];
        }

        return $this->fetch();
    }

    /**
     * A new token, after the site refused the one given: the kept one is
     * dropped only while it is still that one, so a token another request
     * already replaced is not thrown away too.
     */
    public function renew(#[\SensitiveParameter] string $refused): string
    {
        $kept = $this->cache->get($this->key);
        if (!is_array($kept) || ($kept['token'] ?? null) === $refused) {
            $this->cache->delete($this->key);

            return $this->fetch();
        }

        return $this->token();
    }

    /**
     * What var_dump() and print_r() show: the secret is replaced.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['clientId' => $this->clientId, 'clientSecret' => '[redacted]', 'scopes' => $this->scopes];
    }

    private function fetch(): string
    {
        $tokens = $this->oidc->clientCredentials($this->clientId, $this->clientSecret, $this->scopes);

        $now = $this->clock->now()->getTimestamp();
        $usableUntil = $tokens->expiresAt === null ? $now : $tokens->expiresAt->getTimestamp() - self::EXPIRY_MARGIN;
        if ($usableUntil > $now) {
            $this->cache->set($this->key, ['token' => $tokens->accessToken, 'usable_until' => $usableUntil], $usableUntil - $now);
        }

        return $tokens->accessToken;
    }
}

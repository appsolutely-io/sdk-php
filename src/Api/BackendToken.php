<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Cache\TokenLock;
use Appsolutely\Sdk\Exception\AppsolutelyException;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Oidc\OpenIdClient;
use Closure;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * The access token the site backend's machine client is granted, asked for
 * when first needed and kept in the cache the application shares between
 * requests until shortly before it expires, so a backend under PHP-FPM asks
 * once per token rather than once per request.
 *
 * Every process sharing the cache would find the token due at the same
 * moment, so each read in the token's last REFRESH_WINDOW seconds replaces
 * it with a chance that grows to certain as the window runs out; with a
 * TokenLock, the processes that still meet ask for it once.
 *
 * A call in the window holds a token still good, so replacing it early
 * never holds that call up: it asks once, without retrying, only when the
 * lock is free, and sends the kept token whatever happens. Only a call
 * with no usable token waits for the lock and retries.
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

    /**
     * Seconds before the margin in which a token is replaced early, at a
     * random point; at most half the token's usable life.
     */
    public const int REFRESH_WINDOW = 60;

    /**
     * Seconds the lock around asking for a token is held at most: a request
     * and its retries rarely take longer, and a holder that died frees it.
     */
    public const int LOCK_SECONDS = 30;

    /**
     * Seconds a token answered without `expires_in` is taken to live: short,
     * since the site did not say, yet long enough not to ask on every call.
     */
    public const int DEFAULT_LIFETIME = 300;

    private const string KEY_PREFIX = 'appsolutely.backend.token.';

    private string $key;

    private LoggerInterface $logger;

    /** @var Closure(): float a number from 0 to 1 */
    private Closure $random;

    /**
     * @param list<string> $scopes
     * @param (Closure(): float)|null $random where in its window a read replaces the token, from 0 to 1
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
        private ?ClientAuthentication $authentication = null,
        private ?TokenLock $lock = null,
        ?LoggerInterface $logger = null,
        ?Closure $random = null,
    ) {
        // Lengths first, so no two (issuer, client, scopes) join to one string.
        $scope = implode(' ', $scopes);
        $this->key = self::KEY_PREFIX . hash('sha256', strlen($issuer) . ':' . $issuer . strlen($clientId) . ':' . $clientId . $scope);
        $this->logger = $logger ?? new NullLogger();
        $this->random = $random ?? static fn(): float => random_int(0, 1_000_000) / 1_000_000;
    }

    /**
     * A token to send now: the one kept, or a new one.
     *
     * @phpstan-impure
     */
    public function token(): string
    {
        $kept = $this->kept();
        $now = $this->clock->now()->getTimestamp();
        if ($kept === null || $now >= $kept['usable_until']) {
            return $this->replace();
        }

        $due = $kept['refresh_from'] + ($kept['usable_until'] - $kept['refresh_from']) * ($this->random)();
        if ($now < $due) {
            return $kept['token'];
        }

        try {
            return $this->replaceEarly() ?? $kept['token'];
        } catch (\Throwable $exception) {
            // The kept token is good until its margin: use it and try again
            // on a later read rather than fail a call that need not fail. The
            // class alone: a lock's or provider's message is its own text.
            $this->logger->info(sprintf('Replacing the backend token early failed (%s); the current one is used until it is due.', $exception::class));

            return $kept['token'];
        }
    }

    /**
     * A new token, after the site refused the one given: the kept one is
     * dropped only while it is still that one, so a token another request
     * already replaced is not thrown away too.
     */
    public function renew(#[\SensitiveParameter] string $refused): string
    {
        $kept = $this->kept();
        if ($kept === null || $kept['token'] === $refused) {
            $this->cache->delete($this->key);
        }

        return $this->replace();
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

    /**
     * A token not yet in its refresh window, fetched under the lock when
     * there is one.
     */
    private function replace(): string
    {
        return $this->lock === null
            ? $this->fetchUnlessReplaced()
            : $this->lock->run($this->key . '.lock', self::LOCK_SECONDS, fn(): string => $this->fetchUnlessReplaced());
    }

    /**
     * A new token asked for once, or null when another process holds the
     * lock and so is already asking.
     */
    private function replaceEarly(): ?string
    {
        return $this->lock === null
            ? $this->fetchUnlessReplaced(retried: false)
            : $this->lock->runIfFree($this->key . '.lock', self::LOCK_SECONDS, fn(): string => $this->fetchUnlessReplaced(retried: false));
    }

    /**
     * Another process may have replaced the token while this one waited for
     * the lock; that one is used rather than asked for again.
     */
    private function fetchUnlessReplaced(bool $retried = true): string
    {
        $kept = $this->kept();
        if ($kept !== null && $this->clock->now()->getTimestamp() < $kept['refresh_from']) {
            return $kept['token'];
        }

        return $this->fetch($retried);
    }

    /**
     * @return array{token: string, usable_until: int, refresh_from: int}|null
     */
    private function kept(): ?array
    {
        $kept = $this->cache->get($this->key);
        if (!is_array($kept) || !is_string($kept['token'] ?? null) || !is_int($kept['usable_until'] ?? null)) {
            return null;
        }
        $refreshFrom = $kept['refresh_from'] ?? null;

        return ['token' => $kept['token'], 'usable_until' => $kept['usable_until'], 'refresh_from' => is_int($refreshFrom) ? $refreshFrom : $kept['usable_until']];
    }

    private function fetch(bool $retried): string
    {
        try {
            $tokens = $this->oidc->clientCredentials($this->clientId, $this->clientSecret, $this->scopes, $this->authentication, $retried);
        } catch (AppsolutelyException $exception) {
            // The class alone: a provider's message is its own text.
            $this->logger->warning(sprintf('Asking for the backend token failed (%s).', $exception::class), ['client_id' => $this->clientId]);

            throw $exception;
        }

        $now = $this->clock->now()->getTimestamp();
        $usableUntil = ($tokens->expiresAt?->getTimestamp() ?? $now + self::DEFAULT_LIFETIME) - self::EXPIRY_MARGIN;
        if ($usableUntil > $now) {
            $refreshFrom = $usableUntil - min(self::REFRESH_WINDOW, intdiv($usableUntil - $now, 2));
            $this->cache->set($this->key, ['token' => $tokens->accessToken, 'usable_until' => $usableUntil, 'refresh_from' => $refreshFrom], $usableUntil - $now);
        }

        return $tokens->accessToken;
    }
}

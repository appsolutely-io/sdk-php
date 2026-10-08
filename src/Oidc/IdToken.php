<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Http\Json;

/**
 * An ID token whose signature and claims have been verified.
 */
final readonly class IdToken
{
    /**
     * The time of the member's original authentication, which OpenID
     * Connect Core section 12.2 holds every refreshed ID token to: this
     * token's auth_time, or, when it came from a refresh and carries none,
     * the auth_time of the ID token it refreshed. Kept apart from the
     * claims, which stay exactly as signed.
     */
    public int|float|null $authTime;

    /**
     * @internal only the verifier and OpenIdClient::refresh() create one, so holding an IdToken means its checks passed
     *
     * @param array<string, mixed> $claims
     * @param int|float|null $originalAuthTime the auth_time to keep when the claims carry none
     */
    public function __construct(
        public string $raw,
        public string $subject,
        public array $claims,
        int|float|null $originalAuthTime = null,
    ) {
        $claimed = $claims['auth_time'] ?? null;
        $this->authTime = is_int($claimed) || is_float($claimed) ? $claimed : $originalAuthTime;
    }

    /**
     * What a session keeps so the next OpenIdClient::refresh() can compare
     * against this token, as plain values that survive JSON: give it back
     * with fromTrustedStorage(). It holds the raw ID token, so store it
     * where the refresh token is stored, never in a cookie or a URL.
     *
     * @return array{raw: string, claims: array<string, mixed>, auth_time: int|float|null}
     */
    public function toArray(): array
    {
        return ['raw' => $this->raw, 'claims' => $this->claims, 'auth_time' => $this->authTime];
    }

    /**
     * Rebuilds an ID token from what toArray() returned and the integrator
     * stored. This verifies nothing: it trusts the storage, so only pass
     * values the integrator's own server kept (a server-side session, a
     * database row), never values that came with a request. Its shape is
     * checked so a mistake in storing it is caught here rather than as a
     * refresh refused for the wrong reason.
     *
     * @param array<mixed> $stored
     */
    public static function fromTrustedStorage(#[\SensitiveParameter] array $stored): self
    {
        $raw = $stored['raw'] ?? null;
        $claims = $stored['claims'] ?? null;
        if (
            !is_string($raw) || $raw === ''
            || !is_array($claims) || ($claims !== [] && array_is_list($claims))
            || !array_key_exists('auth_time', $stored)
        ) {
            throw self::notStored();
        }

        $claims = Json::stringKeys($claims);
        $subject = $claims['sub'] ?? null;
        $authTime = $stored['auth_time'];
        if (!is_string($subject) || $subject === '' || ($authTime !== null && !is_int($authTime) && !is_float($authTime))) {
            throw self::notStored();
        }

        // toArray() writes the claim's own auth_time when there is one.
        $claimed = $claims['auth_time'] ?? null;
        if ((is_int($claimed) || is_float($claimed)) && ($authTime === null || (float) $claimed !== (float) $authTime)) {
            throw self::notStored();
        }

        return new self($raw, $subject, $claims, $authTime);
    }

    private static function notStored(): InvalidArgumentValueException
    {
        return new InvalidArgumentValueException('A stored ID token must be the array IdToken::toArray() returned: raw, claims with a sub, and auth_time.');
    }
}

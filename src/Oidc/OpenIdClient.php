<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\AuthorizationResponseException;
use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Support\Untrusted;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * OpenID Connect sign-in and OAuth 2.0 tokens against one Appsolutely issuer.
 *
 * A sign-in is two requests apart: authorizationUrl() before the redirect,
 * exchangeCallback() when the member comes back with the AuthorizationRequest
 * the caller kept in the session in between.
 */
final readonly class OpenIdClient
{
    /** Parameters the flow's security rests on; a caller cannot override them. */
    private const array RESERVED_PARAMETERS = [
        'response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'nonce', 'code_challenge', 'code_challenge_method', 'max_age',
    ];

    /**
     * A cached machine token is replaced this long before it expires, so a
     * request that starts with it does not reach the API with a dead token.
     */
    private const int MACHINE_TOKEN_MARGIN = 60;

    private Discovery $discovery;

    /**
     * @internal obtain it from Client::oidc(), which supplies the transport, cache and clock
     */
    public function __construct(
        private Config $config,
        private HttpTransport $http,
        private CacheInterface $cache,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
        $this->discovery = new Discovery($http, $cache, $config->issuer);
    }

    /**
     * Refused like Config's, whose secret this holds.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('An OpenIdClient holds the client secret and is not serialized; build it again from its Config.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('An OpenIdClient holds the client secret and is not unserialized; build it again from its Config.');
    }

    public function metadata(): ProviderMetadata
    {
        return $this->discovery->metadata();
    }

    /**
     * @param list<string> $scopes `openid` is added when missing
     * @param array<string, string> $parameters further authorization parameters, such as `prompt` or `login_hint`
     * @param int|null $maxAge seconds since the member last authenticated beyond which the provider must ask
     *                         again (OpenID Connect Core section 3.1.2.1); the ID token's auth_time is then checked
     */
    public function authorizationUrl(string $redirectUri, array $scopes = ['openid'], array $parameters = [], ?int $maxAge = null): AuthorizationRequest
    {
        if ($maxAge !== null && $maxAge < 0) {
            throw new InvalidArgumentValueException('The max_age must be zero or a positive number of seconds.');
        }

        foreach (array_keys($parameters) as $name) {
            if (in_array($name, self::RESERVED_PARAMETERS, true)) {
                throw new InvalidArgumentValueException(sprintf('The authorization parameter "%s" is set by the client and cannot be passed in.', $name));
            }
        }

        $metadata = $this->metadata();
        // RFC 8414 section 2: no methods listed means PKCE is not supported.
        if (!in_array('S256', $metadata->codeChallengeMethodsSupported ?? [], true)) {
            throw new DiscoveryException('The provider does not advertise the PKCE S256 method in code_challenge_methods_supported.');
        }

        if (!in_array('openid', $scopes, true)) {
            array_unshift($scopes, 'openid');
        }

        $state = RandomToken::generate();
        $nonce = RandomToken::generate();
        $pkce = Pkce::generate();

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => 'S256',
            ...($maxAge === null ? [] : ['max_age' => (string) $maxAge]),
            ...$parameters,
        ], '', '&', PHP_QUERY_RFC3986);

        $endpoint = $metadata->authorizationEndpoint;
        $url = $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . $query;

        return new AuthorizationRequest($url, $redirectUri, $state, $nonce, $pkce->verifier, $maxAge);
    }

    /**
     * Checks the query the member was redirected back with and returns the
     * authorization code.
     *
     * The `iss` parameter is compared first (RFC 9207 section 2.4) so that a
     * response from another provider, mixed in by an attacker, is refused
     * before anything in it is trusted; then `state` (RFC 6749 section 10.12).
     *
     * @param array<mixed> $query
     */
    public function validateCallback(array $query, AuthorizationRequest $request): string
    {
        $metadata = $this->metadata();

        if (array_key_exists('iss', $query)) {
            if ($query['iss'] !== $metadata->issuer) {
                throw new AuthorizationResponseException('The authorization response\'s iss parameter names another issuer.');
            }
        } elseif ($metadata->authorizationResponseIssParameterSupported) {
            throw new AuthorizationResponseException('The authorization response has no iss parameter, which this provider always sends.');
        }

        $state = $query['state'] ?? null;
        if (!is_string($state) || !hash_equals($request->state, $state)) {
            throw new AuthorizationResponseException('The authorization response\'s state is not the one this client sent.');
        }

        $error = OAuthException::fromFields($query);
        if ($error !== null) {
            throw $error;
        }

        $code = $query['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new AuthorizationResponseException('The authorization response carries no code.');
        }

        return $code;
    }

    /**
     * Exchanges the code and verifies the ID token against the nonce of the
     * request it answers.
     */
    public function exchangeCode(string $code, AuthorizationRequest $request): TokenSet
    {
        $fields = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $request->redirectUri,
            'code_verifier' => $request->codeVerifier,
        ]);

        return $this->tokenSet($fields, $request->nonce, idTokenRequired: true, maxAge: $request->maxAge);
    }

    /**
     * @param array<mixed> $query
     */
    public function exchangeCallback(array $query, AuthorizationRequest $request): TokenSet
    {
        return $this->exchangeCode($this->validateCallback($query, $request), $request);
    }

    /**
     * A refreshed ID token carries no nonce to compare; its signature,
     * issuer, audience and times are verified, and it must describe the same
     * authentication as the original. OpenID Connect Core section 12.2: its
     * sub and azp "MUST be the same as in the ID Token issued when the
     * original authentication occurred" (no azp if the original had none),
     * and an auth_time "MUST represent the time of the original
     * authentication"; only iat changes.
     *
     * The returned token set always holds the ID token to pass to the next
     * refresh: the refreshed one, or the original when the answer carried
     * none. A refreshed one without auth_time keeps the original's in its
     * authTime, so one refresh cannot loosen the check on the next.
     *
     * @param IdToken $originalIdToken the ID token of the sign-in this refresh token belongs to, or the one the
     *                                 previous refresh returned; keep it with IdToken::toArray() and restore it with
     *                                 IdToken::fromTrustedStorage() when the session is stored as plain values
     * @param list<string> $scopes a narrower scope than the original grant, or none to keep it
     */
    public function refresh(#[\SensitiveParameter] string $refreshToken, IdToken $originalIdToken, array $scopes = []): TokenSet
    {
        $request = ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken];
        if ($scopes !== []) {
            $request['scope'] = implode(' ', $scopes);
        }

        $tokens = $this->tokenSet($this->tokenRequest($request), null, idTokenRequired: false);

        return new TokenSet(
            $tokens->accessToken,
            $tokens->tokenType,
            $tokens->expiresAt,
            $tokens->refreshToken,
            $tokens->scope,
            $tokens->idToken === null ? $originalIdToken : self::sameAuthentication($originalIdToken, $tokens->idToken),
        );
    }

    /**
     * @param int|null $maxAge the max_age the authorization request was sent with, if any
     */
    public function verifyIdToken(string $idToken, ?string $nonce, ?string $accessToken = null, ?int $maxAge = null): IdToken
    {
        $metadata = $this->metadata();

        return (new IdTokenVerifier(
            new KeySet($this->http, $this->cache, $this->clock, $this->logger, $metadata->jwksUri),
            $this->clock,
            $this->config->issuer,
            $this->config->clientId,
            $metadata->idTokenSigningAlgValuesSupported,
            $this->config->clockLeeway,
        ))->verify($idToken, $nonce, $accessToken, $maxAge);
    }

    /**
     * The member's claims from the userinfo endpoint.
     *
     * Pass the ID token's subject as $expectedSubject: OpenID Connect Core
     * section 5.3.2 requires the client to check that both describe the same
     * member, since the access token alone does not say whose it is.
     *
     * @return array<string, mixed>
     */
    public function userInfo(#[\SensitiveParameter] string $accessToken, ?string $expectedSubject = null): array
    {
        $endpoint = $this->metadata()->userinfoEndpoint
            ?? throw new DiscoveryException('The provider publishes no userinfo_endpoint.');

        $response = $this->http->get($endpoint, ['Authorization' => 'Bearer ' . $accessToken]);
        $claims = $this->successfulJson($response, $endpoint);

        $subject = $claims['sub'] ?? null;
        if (!is_string($subject) || $subject === '') {
            throw new UnexpectedResponseException('The userinfo response has no sub.', $response->getStatusCode());
        }
        if ($expectedSubject !== null && !hash_equals($expectedSubject, $subject)) {
            throw new UnexpectedResponseException('The userinfo response\'s sub is not the ID token\'s.', $response->getStatusCode());
        }

        return $claims;
    }

    /**
     * Revokes an access or refresh token (RFC 7009). The provider answers
     * success for a token it does not know, so this does not say the token
     * existed.
     */
    public function revoke(#[\SensitiveParameter] string $token, ?string $tokenTypeHint = null): void
    {
        $endpoint = $this->metadata()->revocationEndpoint
            ?? throw new DiscoveryException('The provider publishes no revocation_endpoint.');

        $fields = ['token' => $token];
        if ($tokenTypeHint !== null) {
            $fields['token_type_hint'] = $tokenTypeHint;
        }

        [$fields, $headers] = $this->authenticate($fields);
        $response = $this->http->postForm($endpoint, $fields, $headers);
        $status = $response->getStatusCode();

        if (!HttpTransport::isSuccessful($response)) {
            throw OAuthException::fromResponse($response)
                ?? new UnexpectedResponseException(sprintf('POST %s answered %d.', Untrusted::text($endpoint, Untrusted::MAX_LONG_LENGTH), $status), $status);
        }
    }

    /**
     * A client_credentials token for the party's own API calls, reused from
     * the cache until shortly before it expires.
     *
     * @param list<string> $scopes
     */
    public function machineToken(array $scopes = []): TokenSet
    {
        sort($scopes);
        $scope = implode(' ', $scopes);
        $key = 'appsolutely.oidc.machine_token.' . hash('sha256', $this->config->issuer . "\n" . $this->config->clientId . "\n" . $scope);
        $now = $this->clock->now()->getTimestamp();

        $cached = $this->cache->get($key);
        if (
            is_array($cached)
            && is_string($cached['access_token'] ?? null)
            && is_string($cached['token_type'] ?? null)
            && is_int($cached['expires_at'] ?? null)
            && $cached['expires_at'] - self::MACHINE_TOKEN_MARGIN > $now
        ) {
            $cachedScope = $cached['scope'] ?? null;

            return new TokenSet(
                $cached['access_token'],
                $cached['token_type'],
                $this->clock->now()->setTimestamp($cached['expires_at']),
                scope: is_string($cachedScope) ? $cachedScope : null,
            );
        }

        $request = ['grant_type' => 'client_credentials'];
        if ($scope !== '') {
            $request['scope'] = $scope;
        }
        $tokens = $this->tokenSet($this->tokenRequest($request), null, idTokenRequired: false);

        if ($tokens->expiresAt !== null) {
            $ttl = $tokens->expiresAt->getTimestamp() - $now - self::MACHINE_TOKEN_MARGIN;
            if ($ttl > 0) {
                $this->cache->set($key, [
                    'access_token' => $tokens->accessToken,
                    'token_type' => $tokens->tokenType,
                    'expires_at' => $tokens->expiresAt->getTimestamp(),
                    'scope' => $tokens->scope,
                ], $ttl);
            }
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function tokenRequest(array $fields): array
    {
        $endpoint = $this->metadata()->tokenEndpoint;
        [$fields, $headers] = $this->authenticate($fields);

        return $this->successfulJson($this->http->postForm($endpoint, $fields, $headers), $endpoint);
    }

    /**
     * RFC 6749 section 2.3.1: with HTTP Basic, the id and the secret are each
     * form-encoded before they are joined and Base64-encoded.
     *
     * @param array<string, string> $fields
     * @return array{array<string, string>, array<string, string>}
     */
    private function authenticate(array $fields): array
    {
        return match ($this->config->clientAuthentication) {
            ClientAuthentication::ClientSecretBasic => [$fields, [
                'Authorization' => 'Basic ' . base64_encode(urlencode($this->config->clientId) . ':' . urlencode($this->config->clientSecret())),
            ]],
            ClientAuthentication::ClientSecretPost => [
                [...$fields, 'client_id' => $this->config->clientId, 'client_secret' => $this->config->clientSecret()],
                [],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulJson(ResponseInterface $response, string $endpoint): array
    {
        $status = $response->getStatusCode();
        if (!HttpTransport::isSuccessful($response)) {
            throw OAuthException::fromResponse($response)
                ?? new UnexpectedResponseException(sprintf('%s answered %d without an OAuth error.', Untrusted::text($endpoint, Untrusted::MAX_LONG_LENGTH), $status), $status);
        }

        return Json::decodeObject((string) $response->getBody())
            ?? throw new UnexpectedResponseException(sprintf('%s did not answer a JSON object.', Untrusted::text($endpoint, Untrusted::MAX_LONG_LENGTH)), $status);
    }

    /**
     * RFC 6749 section 5.1.
     *
     * @param array<string, mixed> $fields
     */
    private function tokenSet(array $fields, ?string $nonce, bool $idTokenRequired, ?int $maxAge = null): TokenSet
    {
        $accessToken = $fields['access_token'] ?? null;
        $tokenType = $fields['token_type'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new UnexpectedResponseException('The token response has no access_token.', 200);
        }
        // The type is case-insensitive (RFC 6749 section 5.1); only bearer
        // tokens can be sent the way this client sends them.
        if (!is_string($tokenType) || strcasecmp($tokenType, 'Bearer') !== 0) {
            throw new UnexpectedResponseException('The token response\'s token_type is not Bearer.', 200);
        }

        $expiresIn = $fields['expires_in'] ?? null;
        $expiresAt = is_int($expiresIn) || (is_string($expiresIn) && ctype_digit($expiresIn))
            ? $this->clock->now()->modify(sprintf('+%d seconds', (int) $expiresIn))
            : null;

        $idToken = $fields['id_token'] ?? null;
        $verified = null;
        if (is_string($idToken)) {
            $verified = $this->verifyIdToken($idToken, $nonce, $accessToken, $maxAge);
        } elseif ($idTokenRequired) {
            throw new IdTokenException('The token response carries no id_token.');
        }

        $refreshToken = $fields['refresh_token'] ?? null;
        $scope = $fields['scope'] ?? null;

        return new TokenSet(
            $accessToken,
            $tokenType,
            $expiresAt,
            is_string($refreshToken) ? $refreshToken : null,
            is_string($scope) ? $scope : null,
            $verified,
        );
    }

    /**
     * The refreshed token, with the original's auth_time kept when it
     * carries none. iss and aud need no comparison here: both tokens were
     * verified against the configured issuer and this client id alone.
     */
    private static function sameAuthentication(IdToken $original, IdToken $refreshed): IdToken
    {
        if ($refreshed->subject !== $original->subject) {
            throw new IdTokenException('The refreshed ID token\'s sub is not the original ID token\'s.');
        }

        if (($refreshed->claims['azp'] ?? null) !== ($original->claims['azp'] ?? null)) {
            throw new IdTokenException('The refreshed ID token\'s azp is not the original ID token\'s.');
        }

        // Compared as numbers: JSON writes one instant as 1700000000 or 1700000000.0 alike.
        if ($original->authTime !== null && $refreshed->authTime !== null && (float) $original->authTime !== (float) $refreshed->authTime) {
            throw new IdTokenException('The refreshed ID token\'s auth_time is not the original authentication\'s.');
        }

        return $refreshed->authTime === null
            ? new IdToken($refreshed->raw, $refreshed->subject, $refreshed->claims, $original->authTime)
            : $refreshed;
    }
}

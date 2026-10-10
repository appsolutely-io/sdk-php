# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Client` and `Config`, built on PSR-18/17 HTTP, PSR-16 cache, PSR-20 clock and PSR-3 logger interfaces, with an HTTP client discovered when none is given.
- OpenID Connect sign-in through `Client::oidc()`: discovery and the signing keys cached per `Cache-Control` for a day at most, an authorization URL with `state`, `nonce`, PKCE S256 and an optional `max_age` whose `auth_time` is then checked, callback validation including the RFC 9207 `iss` parameter, the code exchange, ID token verification against the provider's RS256/ES256 keys, userinfo and RFC 7009 revocation.
- Token refresh that holds every refreshed ID token to the original authentication (OpenID Connect Core section 12.2): the original ID token is required, the result always carries the ID token to keep for the next refresh (the original when the provider sends none), and a refreshed token without `auth_time` keeps the original's in `IdToken::$authTime`.
- `IdToken::toArray()` and `IdToken::fromTrustedStorage()` to keep the ID token in a session stored as JSON; the latter trusts the integrator's own storage and verifies nothing.
- `Exception\OAuthException` carrying the OAuth error code returned by the provider.
- Every exception an integrator may catch in the `Appsolutely\Sdk\Exception` namespace, all implementing `Exception\AppsolutelyException`. A refused argument is an `Exception\InvalidArgumentValueException`, or a subclass naming what was refused: `InvalidConfigException` for a `Config` value only, `InvalidSecretException` for a webhook signing secret, `InvalidCacheKeyException` for a cache key.
- `Webhooks\Verifier` for Standard Webhooks v1 deliveries, including secret rotation with secrets of 24 to 64 bytes and a timestamp tolerance of five minutes by default, configurable from 1 to 3600 seconds, returning a typed `Webhooks\Event`.
- `Webhooks\Deliveries` to acknowledge a retried or redelivered event without running its handler twice, with an optional namespace per endpoint so several endpoints can share one cache.
- `Testing\WebhookFactory` to build deliveries signed exactly as Appsolutely signs them.
- `Exception\ApiException` for a refusal from the site: an RFC 9457 problem's `type`, `title`, `status`, `detail`, `instance`, the validation `errors`, every other member through `extension()`, the `X-Request-Id`, `Retry-After` in seconds, the `WWW-Authenticate` challenge and the rate-limit budget. `ValidationFailedException`, `NotFoundException`, `UnauthenticatedException`, `ForbiddenException`, `ConflictException`, `RateLimitedException` and `ServiceUnavailableException` cover the cases callers branch on; any other refusal, an unknown `type` included, is the base class. An error body that is not a problem keeps its raw text in `$body`, and a broken problem never fails the parse.
- `Api\RateLimit` and `Api\RateLimitQuota`: the `RateLimit-Policy` and `RateLimit` fields of draft-ietf-httpapi-ratelimit-headers-11 read as Structured Field lists and joined by quota name (`quota`, `window`, `remaining`, `reset`), with `exhausted()` naming the spent quota that turns over last. A field that does not parse is ignored whole, never failing the call.

### Changed

- **BREAKING:** `Config` describes one site: it takes the site's `baseUrl` first (its origin, held to the issuer's HTTPS rules and refused with a path), then the site's `issuer`, the OAuth client that signs its members in, and an optional `apiToken`, the administrator token for server-side calls, which is shown as `[redacted]` and refused when it holds a space or a control character.
- `Exception\InvalidArgumentException` is renamed `Exception\InvalidArgumentValueException`, so an import is not mistaken for PHP's own class; `Webhooks\Verifier` and `Webhooks\Deliveries` throw it instead of `InvalidConfigException` for their own arguments.
- `OpenIdClient::refresh()` requires the original ID token; it is no longer nullable.
- `ProviderMetadata::$userinfoEndpoint` is renamed `$userInfoEndpoint`, cased like `userInfo()`.
- `ProviderMetadata` can only be built through `fromArray()`, and `ProviderMetadata::fromArray()` and `Config::clientSecret()` are internal.
- The User-Agent sends `dev` as the SDK version when Composer reports no real one.

### Removed

- **BREAKING:** `OpenIdClient::machineToken()` and the `client_credentials` grant behind it. Neither credential the client sends to a site comes from the token endpoint: server-side calls use the administrator token issued on the site, and calls made as a member use that member's access token.

### Security

- The issuer and every discovered endpoint must use HTTPS (plain HTTP only on `localhost`, `127.0.0.1` or `[::1]`) and must not contain user info, a backslash, whitespace or a control character, which URL parsers read differently.
- An ID token's `aud` must be a string or a list of strings.
- A signed webhook delivery whose body names another event id than its `webhook-id` header is refused.
- The client secret and webhook signing secrets are shown as `[redacted]` by `var_dump()` and `print_r()`, and `Config`, `Client`, `OpenIdClient`, `Webhooks\Verifier` and `Testing\WebhookFactory` refuse to be serialized.
- Every parameter that carries a secret or a token is marked `#[\SensitiveParameter]`.
- Text that came over the network (discovered endpoints, the HTTP client's error, the JWT library's error, OAuth errors, unverified token headers) reaches exception messages and logs only as short printable text.
- The key set is kept for at least a minute whatever its `Cache-Control`, so ID tokens naming unknown keys refetch it at most once a minute for one cache.
- `request`, `request_uri`, `response_mode` and `claims` are refused as extra authorization parameters, like the ones the client sets itself.

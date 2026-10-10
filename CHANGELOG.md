# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Client` and `Config`, built on PSR-18/17 HTTP, PSR-16 cache, PSR-20 clock and PSR-3 logger interfaces, with an HTTP client discovered when none is given.
- OpenID Connect sign-in through `Client::oidc()`: discovery and the signing keys cached per `Cache-Control` for a day at most, an authorization URL with `state`, `nonce`, PKCE S256 and an optional `max_age` whose `auth_time` is then checked, callback validation including the RFC 9207 `iss` parameter, the code exchange, ID token verification against the provider's RS256/ES256 keys, userinfo, RFC 7009 revocation and a cached `client_credentials` machine token for the party's API calls (the calls themselves are not wrapped yet).
- Token refresh that holds every refreshed ID token to the original authentication (OpenID Connect Core section 12.2): the original ID token is required, the result always carries the ID token to keep for the next refresh (the original when the provider sends none), and a refreshed token without `auth_time` keeps the original's in `IdToken::$authTime`.
- `IdToken::toArray()` and `IdToken::fromTrustedStorage()` to keep the ID token in a session stored as JSON; the latter trusts the integrator's own storage and verifies nothing.
- `Exception\OAuthException` carrying the OAuth error code returned by the provider.
- Every exception an integrator may catch in the `Appsolutely\Sdk\Exception` namespace, all implementing `Exception\AppsolutelyException`. A refused argument is an `Exception\InvalidArgumentValueException`, or a subclass naming what was refused: `InvalidConfigException` for a `Config` value only, `InvalidSecretException` for a webhook signing secret, `InvalidCacheKeyException` for a cache key.
- `Webhooks\Verifier` for Standard Webhooks v1 deliveries, including secret rotation with secrets of 24 to 64 bytes and a timestamp tolerance of five minutes by default, configurable from 1 to 3600 seconds, returning a typed `Webhooks\Event`.
- `Webhooks\Deliveries` to acknowledge a retried or redelivered event without running its handler twice, with an optional namespace per endpoint so several endpoints can share one cache.
- `Testing\WebhookFactory` to build deliveries signed exactly as Appsolutely signs them.
- `Webhooks\Events\TypedEvent::from()` to read a verified `Webhooks\Event` into a typed event for every type the site sends: article, page, product, form, order, payment, refund, referral reward, subscription and account events and `webhook.ping`, with `Webhooks\EventType` naming each type. Times are UTC `DateTimeImmutable`, ids strings and amounts integer minor units beside their currency; fields the client does not name stay readable in `$extra`. An unknown type becomes `Events\UnknownEvent` rather than an exception, and a known type whose data does not have its shape throws the new `Exception\UnexpectedPayloadException`.

### Changed

- `Exception\InvalidArgumentException` is renamed `Exception\InvalidArgumentValueException`, so an import is not mistaken for PHP's own class; `Webhooks\Verifier` and `Webhooks\Deliveries` throw it instead of `InvalidConfigException` for their own arguments.
- `OpenIdClient::refresh()` requires the original ID token; it is no longer nullable.
- `ProviderMetadata::$userinfoEndpoint` is renamed `$userInfoEndpoint`, cased like `userInfo()`.
- `ProviderMetadata` can only be built through `fromArray()`, and `ProviderMetadata::fromArray()` and `Config::clientSecret()` are internal.
- The User-Agent sends `dev` as the SDK version when Composer reports no real one.

### Security

- The issuer and every discovered endpoint must use HTTPS (plain HTTP only on `localhost`, `127.0.0.1` or `[::1]`) and must not contain user info, a backslash, whitespace or a control character, which URL parsers read differently.
- An ID token's `aud` must be a string or a list of strings.
- A signed webhook delivery whose body names another event id than its `webhook-id` header is refused.
- The client secret and webhook signing secrets are shown as `[redacted]` by `var_dump()` and `print_r()`, and `Config`, `Client`, `OpenIdClient`, `Webhooks\Verifier` and `Testing\WebhookFactory` refuse to be serialized.
- Every parameter that carries a secret or a token is marked `#[\SensitiveParameter]`.
- Text that came over the network (discovered endpoints, the HTTP client's error, the JWT library's error, OAuth errors, unverified token headers) reaches exception messages and logs only as short printable text.
- The key set is kept for at least a minute whatever its `Cache-Control`, so ID tokens naming unknown keys refetch it at most once a minute for one cache.
- `request`, `request_uri`, `response_mode` and `claims` are refused as extra authorization parameters, like the ones the client sets itself.

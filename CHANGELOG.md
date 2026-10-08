# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Client` and `Config`, built on PSR-18/17 HTTP, PSR-16 cache, PSR-20 clock and PSR-3 logger interfaces, with an HTTP client discovered when none is given.
- OpenID Connect sign-in through `Client::oidc()`: discovery and the signing keys cached per `Cache-Control` for a day at most, with the issuer and every discovered endpoint required to use HTTPS (plain HTTP only on `localhost`, `127.0.0.1` or `[::1]`), an authorization URL with `state`, `nonce`, PKCE S256 and an optional `max_age` whose `auth_time` is then checked, callback validation including the RFC 9207 `iss` parameter, the code exchange, ID token verification against the provider's RS256/ES256 keys, refresh that refuses an ID token for another member or authentication than the original, userinfo, RFC 7009 revocation and a cached `client_credentials` token.
- `Exception\OAuthException` carrying the OAuth error code returned by the provider.
- Every exception an integrator may catch in the `Appsolutely\Sdk\Exception` namespace, all implementing `Exception\AppsolutelyException`.
- `Webhooks\Verifier` for Standard Webhooks v1 deliveries, including secret rotation and a five-minute timestamp tolerance, returning a typed `Webhooks\Event`.
- `Webhooks\Deliveries` to acknowledge a retried or redelivered event without running its handler twice.
- `Testing\WebhookFactory` to build deliveries signed exactly as Appsolutely signs them.

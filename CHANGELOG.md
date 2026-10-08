# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Client` and `Config`, built on PSR-18/17 HTTP, PSR-16 cache, PSR-20 clock and PSR-3 logger interfaces, with an HTTP client discovered when none is given.
- OpenID Connect sign-in through `Client::oidc()`: discovery cached per `Cache-Control`, an authorization URL with `state`, `nonce` and PKCE S256, callback validation including the RFC 9207 `iss` parameter, the code exchange, ID token verification against the provider's RS256/ES256 keys, refresh, userinfo, RFC 7009 revocation and a cached `client_credentials` token.
- `Oidc\OAuthException` carrying the OAuth error code returned by the provider.
- `Webhooks\Verifier` for Standard Webhooks v1 deliveries, including secret rotation and a five-minute timestamp tolerance, returning a typed `Webhooks\Event`.
- `Webhooks\Deliveries` to acknowledge a retried or redelivered event without running its handler twice.
- `Testing\WebhookFactory` to build deliveries signed exactly as Appsolutely signs them.

# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

The first release is being prepared. The client is held to the Site API and Administrator API documents at aio commit `c44982c39` on aio's default branch, after v0.43.0, which no aio release contains yet; use a site running that commit or a newer one.

### Added

- `Client` and `Config`, built on the PSR-18/17 HTTP, PSR-16 cache, PSR-20 clock and PSR-3 logger interfaces, with an HTTP client discovered when none is given. `Config` describes one site: its base URL, its OpenID issuer, the member sign-in client and, optionally, the backend's machine client. Request and connect timeouts are applied to a Guzzle or Symfony HttpClient the SDK finds for itself.
- OpenID Connect sign-in through `Client::oidc()`: discovery and signing keys cached per `Cache-Control` for a day at most, an authorization URL with `state`, `nonce` and PKCE S256, the code exchange with ID token verification, refresh that holds every refreshed ID token to the original authentication (OpenID Connect Core section 12.2), user info and RFC 7009 revocation. `IdToken::toArray()` and `IdToken::fromTrustedStorage()` keep an ID token in a session.
- The Site API on three clients, by who may call each operation: `Client::api()` for the calls anyone may make, `Client::forMember($accessToken)->api()` for a signed-in member and `Client::backend()` for the site's own backend, whose token is granted by the client-credentials grant, cached, and asked for once more on `invalid-token`. Every operation of the pinned document has one typed method, held to it by the package's contract tests, and `raw()` offers the untyped calls on each client.
- The member's own data: profile and terms, addresses, orders, entitlements with their `kind`, the billing entry, push devices, referrals, checkout and portal sessions, store purchases, subscriptions and their changes, trials, and organisations with their members, invitations, roles, seats and pools, transfers, billing reviews and operations, recovery bindings, replacements and dissolution.
- The backend's operations: the product catalogue, account states, entitlements and feature values, roles and role assignments, organisation roles, seats, checkout and portal sessions, store purchases (`StorePurchaseTransaction::$belongsToYou` says whether the answer credits the purchase to the caller), fulfilment of digital deliveries, webhook endpoints and their deliveries, return targets and test clocks.
- Address reference data for a member and the backend alike, through `addressReference()`: countries, address formats, areas and postal codes. The backend's client needs the `reference:read` scope.
- Discovery, signing keys, distribution versions with their release-channel targets, the update a Tauri desktop app is offered (`distributionVersions()->tauriUpdate()`, null when it is up to date), and the individual OAuth token, device-authorization, revocation and user-info requests, with per-grant body value objects and explicit client authentication.
- Member sync feeds: pulls and pushes of the member's records.
- `Admin\AdminClient` and `Admin\AdminConfig` for the site's Administrator API, called with an administrator credential: articles, pages, products, orders, form entries and webhook deliveries. A read answers the record with its `ETag`, and an update or a delete is sent over that version as `If-Match`.
- Readonly models in `Appsolutely\Sdk\Model`: string ids, `DateTimeImmutable` times in UTC, money as integer minor units, a constant for every value the document enumerates, and what the site adds beyond the document in each model's `$extra`. A required field that is missing or of another type is an `UnexpectedResponseException` naming the schema and the field.
- Cursor lists as `Api\Paginator`, which fetches each next page only when iteration reaches it, and `Api\Page` for one page.
- Keyed writes: an `Idempotency-Key` passed in or generated as a UUID v4, kept across retries, with `KeyedResult::$replayed` for the stored answer to an earlier attempt.
- Every refusal as an `Exception\ApiException` subclass carrying the RFC 9457 problem, and `Api\RateLimit` reading the `RateLimit-Policy` and `RateLimit` fields of draft-ietf-httpapi-ratelimit-headers. Retries follow `Retry-After` within a bounded policy.
- `Webhooks\Verifier` for Standard Webhooks v1 deliveries, with secret rotation and a configurable timestamp tolerance, `Webhooks\Deliveries` to run each event's handler once, and `Webhooks\Events\TypedEvent::from()` for a typed event per type the site sends.
- Test doubles: `Testing\FakeClient` and `Testing\FakeAdminClient` answer arranged operations in memory and record the calls, and `Testing\WebhookFactory` builds deliveries signed as the site signs them.
- `Contract::REVISION` and `Contract::RELEASE`, the aio commit and release the client is held to, and `composer pin-contract`, which copies both API documents from an aio checkout and records them.
- Logging through the PSR-3 logger, with every attempt at `debug` and giving up at `warning`.

### Security

- The issuer and every discovered endpoint must use HTTPS, except on a loopback host, and must not contain user info, a backslash, whitespace or a control character.
- Client secrets, tokens and webhook signing secrets are shown as `[redacted]` by `var_dump()` and `print_r()`, and the objects holding them refuse serialization. Every parameter that carries a secret, a token or the site's JSON is marked `#[\SensitiveParameter]`.
- Text that came over the network reaches exception messages and logs only in a short, printable form.
- A webhook delivery whose body names another event id than its `webhook-id` header is refused.

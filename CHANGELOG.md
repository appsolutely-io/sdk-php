# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] - 2026-10-17

### Added

- `Admin\AdminClient` and `Admin\AdminConfig`: the site's Administrator API (`/api/admin/v1`) called with an administrator credential, an OAuth client holding `admin:*` scopes. Its token is asked for by the client-credentials grant, cached apart from the backend's, and asked for once more on `invalid-token`; a missing scope is a `ForbiddenException` and is neither renewed nor retried. `raw()` offers the untyped calls and refuses any path outside `/api/admin/v1/` before sending.
- Typed Administrator API operations on `AdminClient`, held to the pinned Administrator API document by `AdminOperation` and its contract tests: `articles()` (list, get, create, update, delete), `pages()`, `products()`, `orders()` and `formEntries()` (list, get), and `webhookDeliveries()` (list, get, redeliver). Records are read into `Admin\Model` classes; a read answers `Admin\Versioned`, the record with its `ETag` (an answer naming none is an `UnexpectedResponseException`), and an update or a delete is sent over that version as `If-Match`.
- `SiteApi::put()`, `patch()` and `delete()` take an optional `ifMatch`, and `ApiResponse::etag()` reads an answer's version.
- `Testing\FakeAdminClient` and `Testing\RecordedAdminCall`, the Administrator API in memory for an integrator's tests. A read or an update answers an `ETag` (`FakeAdminClient::ETAG` unless one is arranged), and an update or a delete sent without `If-Match` is refused `428` `precondition-required`, as the site refuses it.
- `composer pin-contract` also pins `docs/api/admin-openapi.yaml`, at the same commit as the Site API document, into `tests/Contract/admin-openapi.yaml`.
- `backend()->fulfilments()->create($token, $deliveryPayload, $channel)`: the backend fulfils a pending digital delivery with the `fulfilments:write` scope, once under an `Idempotency-Key`, and reads the delivery token as it stands (`Model\Fulfilment`). A token no longer pending is a `ConflictException`.
- `Testing\FakeClient::backend()`: the fake answers the backend's token request in memory with `FakeClient::BACKEND_TOKEN`, so the backend's operations can be arranged and recorded like any other.

### Changed

- **BREAKING:** the client is held to the Site API document of the site software v0.32.0 (`Contract::REVISION`). The site no longer answers an administrator on the Site API: an administrator's work goes through `AdminClient` and the Administrator API.
- **BREAKING:** `Client::api()` returns `Api\PublicApi`, the calls anyone may make (`version()`, `openApiDocument()`, `magicLink()` and an untyped `raw()`), sent without a credential. `Api\AdministratorApi` is removed.
- **BREAKING:** the product catalogue is the backend's alone: `Resource\Products` moves to `Resource\Backend\Products`, reached from `backend()->products()` with the `catalogue:read` scope.
- **BREAKING:** `Model\EndpointWebhookDelivery` holds the delivery's fields itself (`$webhookId`, `$type`, `$status`, `$attempts` and the times), as its schema does, in place of a nested `$delivery`.
- **BREAKING:** `Operation::audiences()` is replaced by `Operation::audience()`, since every operation now has one audience.
- **BREAKING:** `Testing\FakeClient::ADMINISTRATOR_TOKEN` is replaced by `FakeClient::BACKEND_TOKEN`.

### Removed

- **BREAKING:** `Config`'s `apiToken` parameter and `Config::apiToken()`: the site issues no administrator token for the Site API. Arguments passed by position after `$clientSecret` move.
- **BREAKING:** the administrator's Site API resources and their operations: `pages()`, `articles()`, `orders()`, `formEntries()`, `accountStates()` (the backend's `backend()->accountStates()` stays), `webhookDeliveries()` and the administrator sync feeds (`sync()`, with `Resource\Sync` and its article, form entry, order, page and product feeds), with `Model\WebhookDelivery` and `Audience::Administrator`. The Administrator API serves the same records through `AdminClient`; the sync feeds have no replacement.

### Migrating administrator calls

Replace the personal token with the administrator credential's client id, secret and scopes:

```php
use Appsolutely\Sdk\Admin\AdminClient;
use Appsolutely\Sdk\Admin\AdminConfig;
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;

// Before
$client = new Client(new Config(baseUrl: $baseUrl, apiToken: $apiToken));
$pages = $client->api()->pages()->list();

// After
$admin = new AdminClient(new AdminConfig(
    baseUrl: $baseUrl,
    issuer: $issuer,
    clientId: $administratorClientId,
    clientSecret: $administratorClientSecret,
    scopes: ['admin:pages:read'],
));
$pages = $admin->pages()->list();
```

## [0.4.0] - 2026-10-16

### Added

- `Member::$terms`, a `MemberTerms`: the site's `current` terms version, null when it asks for no acceptance, and whether the member has `accepted` it. A member who has not is asked to in the site's member area; the Site API stays open to them.

### Changed

- **BREAKING:** the client is held to the Site API document of the site software v0.30.0 (`Contract::REVISION`), and needs a site at least that new: a member's `terms` is required, so `me()->get()` and `me()->update()` against an older site are refused as an `UnexpectedResponseException`. `Member`'s constructor takes `$terms` before `$extra`.
- An account state's `totals` are declared as integers by entitlement key, which `AccountState::$totals` already read them as; nothing changes for a caller.

## [0.3.0] - 2026-10-14

### Changed

- **BREAKING:** `me()->subscriptions()->change($id, $returnUrl, $sku, $quantity)` takes the return address second and requires it, as `resume()` does: the site now refuses a change without `return_url`, since whether the member must confirm it on the site is not known beforehand. Arguments passed by position after `$id` move.
- **BREAKING:** the client is held to the Site API document of the site software v0.27.0 (`Contract::REVISION`), and needs a site at least that new: a product's `sku_prices` and the backend client's `store_currency` are required, so an answer or a `product.*` delivery from an older site is refused as an `UnexpectedResponseException` or an `UnexpectedPayloadException`.
- **BREAKING:** `backend()->accountStates()->list()` walks the accounts as the site now lists them, ordered by when each one's entitlements last changed, and gives a `Paginator<ListedAccountStatePage>`: each item is a whole page, whose `$states` are `ListedAccountState`s (the `AccountState` and its `entitlementsChangedAt`) and whose `$nextChangedSince` is where the next walk starts. `$limit` moves to the second parameter. A cursor kept from an older site is refused as `invalid-cursor`; start the walk again.
- **BREAKING (for a caller branching on the status or the type):** the site refuses a magic-link exchange that still needs the member's second factor as `403` `second-factor-required` (a `ForbiddenException`, no longer an `UnauthenticatedException` with a challenge), and one locked out after too many codes as `429` `rate-limited` (a `RateLimitedException` whose `retryAfter` says when to ask again).
- **BREAKING (for a caller passing a large `$limit`):** a sync pull with a limit above the site's maximum, 500 unless it is configured otherwise, is refused as a `ValidationFailedException`; the site used to cut it to the maximum, which read like the end of the changes.
- **BREAKING (for a `subscription.renewed` subscriber):** `SubscriptionEvent::$period` is read as the document describes each type: required on `subscription.renewed`, whose delivery without it is refused as an `UnexpectedPayloadException`; optional on `subscription.started`; and never on any other type, where a `period` stays in `$subscription->extra`.
- **BREAKING (for a `form.submitted` subscriber):** `FormSubmittedEvent::$formName` is a `string`, never null, as the document describes it; a delivery without it is refused as an `UnexpectedPayloadException`.
- **BREAKING:** `Operation::audience()` is replaced by `Operation::audiences()`, since the catalogue reads answer both the administrator and the backend.

### Added

- `Client::backend()->products()`: the site's backend reads the catalogue (`list()` and `get()`) with its own token and the `catalogue:read` scope, the same operations the administrator calls. `Operation::audiences()` lists every audience an operation is answered for.
- `Product::$skuPrices`, a `ProductSkuPrice` per active sku and currency: the sku's identifier, `price`, `originalPrice`, and the `interval` (`ProductSkuPrice::INTERVAL_MONTH` or `INTERVAL_YEAR`) and `intervalCount` a subscription sku renews on, both null for a sku sold once.
- `BackendClient::$storeCurrency`: the ISO 4217 currency every checkout on the site is priced and paid in.
- `backend()->accountStates()->list()` takes `$changedSince`, to list only the accounts whose entitlements changed at or after it.
- `Webhooks\Event::MODE_PRODUCTION` and `Event::MODE_TEST`, the values an envelope's `mode` takes.
- A `subscription-awaiting-payment` refusal of a checkout or a store purchase intent names, beside the waiting `order`, where the signed-in member pays it: `ConflictException::extension('payment_url')`, null when there is nothing to pay on a page.
- A checkout line, from the backend or the member, may name the sku it buys as `sku`, by the identifier the product lists it under in `skuPrices`; a product sold as several skus needs one. `CheckoutSessionLine::$sku` reads it back, null for a line that named none.

### Fixed

- `oidc()->revoke('')` is refused as an `InvalidArgumentValueException` before anything is sent: RFC 7009 requires the token, and the site now refuses a revocation without one as `invalid_request` rather than answering it as revoked.

## [0.2.0] - 2026-10-14

### Added

- `Order::$paymentUrl`: where to send the signed-in member to finish paying an order the site still awaits payment for, or null.
- `StorePurchaseTransaction::OUTCOME_RECORDED` and `StorePurchaseTransaction::OUTCOME_UNCLAIMED`, now that the document names a transaction's outcomes as a closed set.
- `ReferralTerms::TYPE_CREDIT` and `ReferralTerms::TYPE_CASHBACK`, now that the document names a referral's reward types as a closed set.
- `Testing\FakeClient` challenges an arranged `insufficient-scope` `403` as the site does, with `WWW-Authenticate: Bearer error="insufficient_scope", scope="…"` naming its `required` scope, so `ForbiddenException::$challenge` reads in a test what it reads against a site.

### Changed

- **BREAKING:** the client is held to the Site API document of the site software release after v0.24.0 (`Contract::REVISION`), and needs a site at least that new. Every record's `id` the site sends is now an opaque string (a UUID, an order's order number, a member's account subject) rather than a database key; the models already read ids as strings, but an id stored from an older site no longer finds its record, and an offline client pulls its sync feeds again from the start.
- **BREAKING:** `FormEntry::$isSpam` is read from the JSON boolean the site now sends; an older site's `0` or `1` is refused as an `UnexpectedResponseException`.
- **BREAKING:** `Model\SubscriptionStillBilling` is renamed `Model\SubscriptionSource`, the document's name for it, and names who bills it with `BILLED_BY_SITE`, `BILLED_BY_APP_STORE` and `BILLED_BY_GOOGLE_PLAY`. `StorePurchaseIntent::$mayStillBill` is a list of `SubscriptionSource` rather than of strings: the site always sent objects there, so an intent naming a subscription still billed elsewhere was refused.
- **BREAKING:** `me()->subscriptions()->resume($id, $returnUrl)` sends the address the site brings the member back to after confirming, which the site requires, and answers a `KeyedResult<MemberSubscriptionChange>`; `Model\MemberSubscriptionConfirmation` is removed.
- **BREAKING:** `me()->subscriptions()->change()` takes an optional `$returnUrl` before `$idempotencyKey`, so a key passed by position is now passed by name, and reads a change handed to the member to confirm, which the site answers `202`.
- **BREAKING:** `Order::$updatedAt` and `ReferralReward::$earnedAt` are never null, as the document has always declared them: the site now stamps both on every answer and refuses to send one without, so an answer lacking either is an `UnexpectedResponseException` rather than a null to check.
- **BREAKING:** `RefundEvent::$refund` is a `Model\Refund`, the refund the site now delivers on `refund.requested` and `refund.processed`: its UUID `id`, `reference` (the refund number), `order` (the order number), `payment` (the reference `order.paid` gave the payment), `amount` in minor units, `currency`, `status` (`Refund::STATUS_*`), `createdAt`, `updatedAt` and `refundedAt`. `Model\RecordReference`, which read the old `{id, type}` and nothing else, is removed; a delivery recorded by an older site and redelivered still carries that shape and is refused as an `UnexpectedPayloadException`.

## [0.1.0] - 2026-10-13

### Added

- `Client` and `Config`, built on PSR-18/17 HTTP, PSR-16 cache, PSR-20 clock and PSR-3 logger interfaces, with an HTTP client discovered when none is given. `Config` describes one site: its `baseUrl` (its origin, held to the issuer's HTTPS rules and refused with a path), its `issuer` and the OAuth client that signs its members in, which are optional (`Config::$issuer`, `Config::$clientId` and `Config::clientSecret()` are nullable): the sign-in client is needed only to sign members in and the issuer only for that or the backend client, `Client::oidc()` throws an `InvalidConfigException` when the Config holds none, the sign-in id and secret go together, and either client without an issuer is refused; and an optional `apiToken`, the administrator token for server-side calls, which is shown as `[redacted]` and refused when it holds a space or a control character.
- OpenID Connect sign-in through `Client::oidc()`: discovery and the signing keys cached per `Cache-Control` for a day at most, an authorization URL with `state`, `nonce`, PKCE S256 and an optional `max_age` whose `auth_time` is then checked, callback validation including the RFC 9207 `iss` parameter, the code exchange, ID token verification against the provider's RS256/ES256 keys, userinfo and RFC 7009 revocation.
- Token refresh that holds every refreshed ID token to the original authentication (OpenID Connect Core section 12.2): the original ID token is required, the result always carries the ID token to keep for the next refresh (the original when the provider sends none), and a refreshed token without `auth_time` keeps the original's in `IdToken::$authTime`.
- `IdToken::toArray()` and `IdToken::fromTrustedStorage()` to keep the ID token in a session stored as JSON; the latter trusts the integrator's own storage and verifies nothing.
- `Exception\OAuthException` carrying the OAuth error code returned by the provider.
- Every exception an integrator may catch in the `Appsolutely\Sdk\Exception` namespace, all implementing `Exception\AppsolutelyException`. A refused argument is an `Exception\InvalidArgumentValueException`, or a subclass naming what was refused: `InvalidConfigException` for a `Config` value only, `InvalidSecretException` for a webhook signing secret, `InvalidCacheKeyException` for a cache key.
- `Webhooks\Verifier` for Standard Webhooks v1 deliveries, including secret rotation with secrets of 24 to 64 bytes and a timestamp tolerance of five minutes by default, configurable from 1 to 3600 seconds, returning a typed `Webhooks\Event`.
- `Webhooks\Deliveries` to acknowledge a retried or redelivered event without running its handler twice, with an optional namespace per endpoint so several endpoints can share one cache.
- `Testing\WebhookFactory` to build deliveries signed exactly as Appsolutely signs them.
- Untyped Site API calls through `Client::api()->raw()`, carrying the administrator token, and `Client::forMember($accessToken)->api()->raw()`, carrying a member's access token: `get()`, `post()`, `put()`, `patch()` and `delete()` on paths the site's API document names, joined to the base URL, with `Accept: application/json, application/problem+json`, the SDK `User-Agent` (which sends `dev` as the version when Composer reports no real one) and an `X-Request-Id` that is generated or passed in. A success is an `Api\ApiResponse` with the decoded body, the status, the `Location`, the answered request id and the rate-limit budget.
- Cursor paging of Site API lists: `SiteApi::page()` returns an `Api\Page` with its `items` and `nextCursor`, and `SiteApi::paginate()` an `Api\Paginator` that follows `next_cursor` unchanged, fetching each page only when iteration reaches it, until a page carries none; a next cursor that leads back to a page already given ends the walk with an `Exception\UnexpectedResponseException` once that page's items are given. `Paginator::page()` fetches one page, at a cursor kept from an earlier run. A list in the query is sent as `name[]` once per value. The `limit` runs from 1 to 100; `limit` and `cursor` cannot be passed as filters, so a cursor is never built by hand.
- Idempotent writes through `SiteApi::postIdempotent()`: an `Idempotency-Key` that is passed in (1 to 255 printable ASCII characters) or generated as a UUID v4 and sent as an RFC 9651 String, quoted with `\` and `"` escaped, the same key on every retry of the call, and `ApiResponse::$replayed` from `Idempotent-Replayed`. Reads and keyed writes are retried after a network failure, a 429, a 502, a 503 or a 504, a read also after a 500, and a keyed write's `409` `idempotency-key-in-use` (the same key still running), waiting what `Retry-After` (or a spent `RateLimit` quota) asks or a jittered backoff, under an `Api\RetryPolicy` set in `Config` (2 retries and a 30-second ceiling by default, `RetryPolicy::none()` to turn it off, and a `Clock\Sleeper` to wait some other way). Other refusals, and writes without a key, are never retried.
- `Exception\ApiException` for a refusal from the site: an RFC 9457 problem's `type`, `title`, `status`, `detail`, `instance`, the validation `errors`, every other member through `extension()`, the `X-Request-Id`, `Retry-After` in seconds, the `WWW-Authenticate` challenge and the rate-limit budget. `BadRequestException` (400), `ValidationFailedException`, `NotFoundException`, `UnauthenticatedException`, `ForbiddenException`, `ConflictException`, `RateLimitedException` and `ServerErrorException` (any 5xx, with `ServiceUnavailableException` for a 503 beneath it) cover the cases callers branch on; any other refusal, an unknown `type` included, is the base class. `Exception\TransportException::isNetworkFailure()` tells a network failure (PSR-18's `NetworkExceptionInterface`, or a Guzzle cURL transfer that failed before an answer, such as a connection reset after sending), which reads and keyed writes retry, from a request the HTTP client refused, which is never retried. An error body that is not a problem keeps its raw text in `$body`, and a broken problem never fails the parse.
- `Api\RateLimit` and `Api\RateLimitQuota`: the `RateLimit-Policy` and `RateLimit` fields of draft-ietf-httpapi-ratelimit-headers-11 read as Structured Field lists and joined by quota name (`quota`, `window`, `remaining`, `reset`), with `exhausted()` naming the spent quota that turns over last. A field that does not parse is ignored whole, never failing the call.
- `Webhooks\Events\TypedEvent::from()` to read a verified `Webhooks\Event` into a typed event for every type the site sends: article, page, product, form, order, payment, refund, referral reward, subscription, account and role events, `webhook.ping` and `webhook.rotation_ending`, with `Webhooks\EventType` naming each type. Times are UTC `DateTimeImmutable`, ids strings and amounts integer minor units beside their currency; the records are the same `Model` classes the API returns, and members a model has no property for stay readable in its `$extra`. An unknown type becomes `Events\UnknownEvent` rather than an exception, and a known type whose data does not have its shape throws the new `Exception\UnexpectedPayloadException`.
- A typed method for every operation of the Site API document, grouped by resource, on the client of the audience that may call it. `Client::api()` (the administrator): `pages()`, `articles()` (with `create()` and `update()`), `products()`, `orders()`, `formEntries()`, `accountStates()`, `webhookDeliveries()` (with `redeliver()`) and `sync()`, whose feeds for articles, form entries, orders, pages and products `pull()` changes since a cursor and, for articles, `push()` offline edits; and the operations anyone may call, sent without a credential: `version()`, `openApiDocument()` and `magicLink()` (`request()` and `exchange()`). `Client::backend()` (the site's own backend): `client()`, `accountStates()`, `webhookEndpoints()` (with secret rotations), `deliveries()` (with `redeliver()`), `checkoutSessions()`, `portalSessions()`, `storePurchases()`, `testClocks()`, `returnTargets()`, `featureKeys()`, `featureValues()`, `roles()`, `roleAssignments()` and `seats()`. `Client::forMember()->api()`: `me()` (`get()`, `update()`, `addresses()`, `orders()`, `entitlements()`, `billingEntry()`, `pushDevices()`, `referral()`, `checkoutSessions()`, `portalSessions()`, `storePurchases()`, `subscriptions()`) and `sync()` with the member's address and order feeds.
- Readonly models in `Appsolutely\Sdk\Model` built from what the site sends: string ids, `DateTimeImmutable` times in UTC (RFC 3339, any offset, fractions kept to the microsecond), money as integer minor units beside its currency, and `extra` holding the members a model has no property for. One set serves both API answers and webhook deliveries. An answer that breaks its schema is an `Exception\UnexpectedResponseException` naming the schema and the field, with the schema's name in `$schema`. Lists return an `Api\Paginator` of models, or an `Api\Page` for a list the site answers whole, in one page. Every value the document enumerates for a field is a constant of the model that reads it (`Subscription::STATUS_PAST_DUE`, `CheckoutSession::MODE_TEST`), while the field stays a plain string, so a value a newer site adds is still read.
- Keyed writes (`articles()->create()`, `me()->addresses()->create()`, `webhookDeliveries()->redeliver()`, checkouts, billing-page visits, test clocks, webhook endpoints and their rotations, redeliveries and a member's subscription changes) always send an `Idempotency-Key`, the caller's or a fresh UUID v4, and return an `Api\KeyedResult` with the model, the key sent and whether the answer was replayed.
- `Api\Operation`, every operation of the pinned document with its method, path, audience, success statuses, `Idempotency-Key` and paging.
- `Client::backend()` for the site's own backend: `Config` takes the backend machine client's id and secret, together or not at all, and optionally the scopes to ask for. Its token is asked for from the issuer's token endpoint when first needed, authenticated as `backendClientAuthentication` says (the sign-in client's `clientAuthentication` by default), kept in the cache from `Config` until a minute before it expires (five minutes when the token endpoint answers no `expires_in`), and asked for again once when the site answers `invalid-token`. The token request is retried under the `RetryPolicy` after a network failure, a 429, a 502, a 503 or a 504; each read in the minute before the token is due replaces it at a random point, asking once without retrying and keeping the current one if that fails; and `Config::$tokenLock` takes a `Cache\TokenLock` so the processes sharing the cache ask for a due token once: a replacement made early takes the lock only when it is free (`runIfFree()`) and never waits for it, its one request bounded by `Config::$timeout`, while a call whose token has expired waits for the holder (`run()`).
- `Testing\FakeClient`, the Site API answered in memory for an integrator's tests: answers and problem refusals arranged per operation (a refusal names its title, leaves out an empty detail and refuses an extension member that reuses a standard one as the site does, and carries the site's bearer challenge on a 401), read through the real client and models, and every call recorded with its path values, query, body, key and credential.
- `Contract::REVISION`, the commit of the site software whose API document the client is held to, `Contract::RELEASE`, that commit's release (as `git describe --tags` names it), and `composer pin-contract -- <checkout> <ref>`, which copies that document and writes the commit and release in one run, changing both files or neither; a contract test fails when an operation has no method, a method names an operation or path the document lacks, sits on the wrong client, sends its request to another operation's method or path or reads its answer as another schema than the document gives it, or a model reads a field its schema does not define, skips a required one or reads one as another type, or as more or less nullable, than the schema declares.
- Request timeouts: `Config::$timeout` (30 seconds by default) and `Config::$connectTimeout` (10 seconds) are applied to the HTTP client the SDK finds for itself when it is Guzzle or Symfony HttpClient 6.2 or later (`composer.json` suggests both); any other client found, an older Symfony one included, is used as it is, with a warning logged. A client passed in keeps its own timeouts.
- Logging through the PSR-3 logger: each Site API attempt at `debug` (method, path, status, request id, attempt), giving up on a network failure, a 5xx or a refusal a retry could have passed at `warning`, and a failed backend token request at `warning`; headers, bodies, query strings, tokens and keys are never logged.
- A `Client` built without a PSR-16 cache logs one warning that its memory cache refetches discovery, the signing keys and the backend token in every process.

### Security

- The issuer and every discovered endpoint must use HTTPS (plain HTTP only on `localhost`, `127.0.0.1` or `[::1]`) and must not contain user info, a backslash, whitespace or a control character, which URL parsers read differently.
- An ID token's `aud` must be a string or a list of strings.
- A signed webhook delivery whose body names another event id than its `webhook-id` header is refused.
- The client secret and webhook signing secrets are shown as `[redacted]` by `var_dump()` and `print_r()`, and `Config`, `Client`, `OpenIdClient`, `Webhooks\Verifier` and `Testing\WebhookFactory` refuse to be serialized.
- Every parameter that carries a secret or a token, and every parameter that holds the site's JSON, is marked `#[\SensitiveParameter]`.
- Text that came over the network (discovered endpoints, the HTTP client's error, the JWT library's error, OAuth errors, unverified token headers) reaches exception messages and logs only as short printable text.
- The key set is kept for at least a minute whatever its `Cache-Control`, so ID tokens naming unknown keys refetch it at most once a minute for one cache.
- `request`, `request_uri`, `response_mode` and `claims` are refused as extra authorization parameters, like the ones the client sets itself.

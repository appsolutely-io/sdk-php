# Appsolutely PHP SDK

A framework-free PHP client for one app's own site: it signs the site's members in with OpenID Connect, calls the Site API as the site's administrator or as a signed-in member, and verifies the site's Standard Webhooks deliveries.

A site hosted for you and one you host yourself answer the same paths in the same shapes, so pointing the client from one to the other changes the base URL, the issuer and the credentials, nothing else.

Laravel applications install the bridge, [`appsolutely/sdk-laravel`](https://github.com/appsolutely-io/sdk-laravel), which wires this client into the container, Socialite and the router.

## Requirements

- PHP 8.3 or newer
- A PSR-18 HTTP client (any installed one is discovered)

## Compatibility

The client is written and tested against the Site API document of the site software v0.27.0, the commit `Contract::REVISION` names (`Contract::RELEASE` holds the release; a test fails when this line names another). Every typed method, query parameter, request body and model is held to that document by the package's contract tests.

While the site software is below 1.0.0, a minor release of it may change a path of the document's `v1` in a breaking way, and its changelog opens each such entry with `BREAKING`; this client follows such a change in a release of its own, marked the same way in its changelog. Otherwise the site adds paths and fields but never removes or retypes one. From the site software's 1.0.0, a breaking change is announced as deprecated a release ahead and arrives in a new major release. `$client->api()->version()` reports whether the version is current and, once deprecated, its `deprecatedAt`, `sunsetAt` and `successor`.

- **A newer site** answers everything this client calls. Fields it adds stay readable in each model's `$extra`, an enumerated value it adds reads as the plain string it is (the model's constants name only the values this client knows), and an operation it adds is reachable through the untyped calls of `api()->raw()` until a release of the client types it.
- **An older site** lacks what was added after it. Calling an operation it does not have is refused with an `Exception\ApiException` (a `NotFoundException` for a path it does not have), and an answer without a field this client requires throws an `Exception\UnexpectedResponseException` naming the schema and the field rather than reading a wrong value. Keep the site at least at the version above.

## Installation

The package is not on Packagist yet, so Composer installs it from its GitHub repository:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/appsolutely-io/sdk-php" }
    ]
}
```

```bash
composer require "appsolutely/sdk-php:^0.2"
```

The client finds an installed PSR-18 HTTP client through `php-http/discovery`, which is a Composer plugin; allow it in your `composer.json`:

```json
{
    "config": {
        "allow-plugins": {
            "php-http/discovery": true
        }
    }
}
```

## Configuration

```php
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;

// getenv() returns false for an unset variable, which a string parameter
// refuses under strict_types; casting it would turn a missing secret into ''.
$clientId = getenv('APPSOLUTELY_CLIENT_ID');
$clientSecret = getenv('APPSOLUTELY_CLIENT_SECRET');
$apiToken = getenv('APPSOLUTELY_API_TOKEN');
if ($clientId === false || $clientSecret === false || $apiToken === false) {
    throw new RuntimeException('Set APPSOLUTELY_CLIENT_ID, APPSOLUTELY_CLIENT_SECRET and APPSOLUTELY_API_TOKEN.');
}
// The backend's machine client is optional, and its id and secret go together: both set, or neither.
$backendClientId = getenv('APPSOLUTELY_BACKEND_CLIENT_ID') ?: null;
$backendClientSecret = getenv('APPSOLUTELY_BACKEND_CLIENT_SECRET') ?: null;

$client = new Client(new Config(
    baseUrl: 'https://shop.example.com',           // the site's origin, without a path
    issuer: 'https://shop.example.com',            // the site's OpenID issuer, exactly as its discovery names it
    clientId: $clientId,                           // the site's OAuth client that signs members in
    clientSecret: $clientSecret,
    apiToken: $apiToken,                           // optional: the administrator token issued on the site
    cache: $psr16Cache,                            // your application's shared cache; see below
    backendClientId: $backendClientId,             // optional: the site backend's machine client
    backendClientSecret: $backendClientSecret,     // given with its id, or not at all
));
```

The administrator token is a long-lived credential issued on the site's API-token screen; it is sent on the calls your server makes on its own behalf. Leave it out when your server only acts for signed-in members. Keep it, like the client secret, out of version control.

Only `baseUrl` is always required. The member sign-in client (`clientId` with `clientSecret`) is needed only to sign members in, and the `issuer` only for that or for the backend client: a backend-only integration gives `issuer` and the backend client, an administrator-only one just `apiToken`. `Client::oidc()` throws an `InvalidConfigException` when the Config holds no sign-in client.

The backend client is the machine client registered for your app's backend, apart from the client that signs members in; `Client::backend()` calls as it (see [The site's backend](#the-sites-backend)). `backendScopes` narrows what its token may do; without it, the token carries every scope the client is registered with. `backendClientAuthentication` says how that client authenticates to the token endpoint when it differs from the sign-in client's `clientAuthentication`.

`Config` also takes a PSR-18 client and PSR-17 factories, a PSR-20 clock, a PSR-3 logger, the token-endpoint authentication (`client_secret_basic` by default, or `ClientAuthentication::ClientSecretPost`), the clock leeway for ID tokens (60 seconds by default) and the retry policy for Site API calls (see [Writes that must happen once](#writes-that-must-happen-once)).

### The cache

In production, pass your application's shared PSR-16 cache (Redis, Memcached, APCu or your framework's cache). Without one, the client falls back to a cache in memory that lives as long as the PHP process; under PHP-FPM that is a single request, so every request fetches the discovery document and the signing keys again. The fallback exists so the client works anywhere, not for production, and each client built on it logs one warning saying so.

### Timeouts

PSR-18 has no portable way to set a timeout, and Guzzle's default is to wait forever. When you pass no HTTP client, the SDK finds one and, if it is Guzzle or Symfony HttpClient, builds it with `Config::$timeout` (30 seconds per request by default) and `Config::$connectTimeout` (10 seconds to connect; Symfony HttpClient has no separate connect limit, so its idle timeout is the request timeout). Thirty seconds is above the 20 seconds the site may hold a keyed write that repeats one still running, and far below the minutes a stuck connection would otherwise hold a worker; Stripe's PHP library, for comparison, waits 80 seconds per request and 30 to connect. Any other client it finds is used as it is, with a warning in the log.

A client you pass in keeps its own timeouts, so build it with them:

```php
use GuzzleHttp\Client as Guzzle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$http = new Guzzle(['timeout' => 30, 'connect_timeout' => 10]);
// or
$http = new Psr18Client(HttpClient::create(['timeout' => 30, 'max_duration' => 30]));

$client = new Client(new Config(/* ... */, httpClient: $http));
```

## Signing a member in with OpenID Connect

Two requests: one sends the member to the site's sign-in page, the other receives them back.

```php
// 1. Start: build the URL and keep the request in the session.
$request = $client->oidc()->authorizationUrl(
    redirectUri: 'https://app.example.com/callback',
    scopes: ['openid', 'email'],
);
$_SESSION['appsolutely_sign_in'] = $request;
header('Location: ' . $request->url);

// 2. Callback: validate the response, exchange the code, verify the ID token.
$request = $_SESSION['appsolutely_sign_in'];
unset($_SESSION['appsolutely_sign_in']);

$tokens = $client->oidc()->exchangeCallback($_GET, $request);
$memberId = $tokens->idToken->subject;

// Keep what a later refresh needs; toArray() survives a session stored as JSON.
$_SESSION['appsolutely_tokens'] = [
    'refresh_token' => $tokens->refreshToken,
    'id_token' => $tokens->idToken->toArray(),
];
```

`authorizationUrl()` generates `state`, `nonce` and a PKCE S256 verifier. `exchangeCallback()` checks the `iss` and `state` of the response, exchanges the code with the verifier and verifies the ID token: its signature against the provider's published keys (RS256 or ES256), `iss`, `aud`, `azp`, `exp`, `iat` and the `nonce`. Any failure throws; never sign the member in after an exception.

To require a recent authentication, pass `maxAge:` (in seconds) to `authorizationUrl()`: the provider asks the member to sign in again when their last authentication is older, and the ID token's `auth_time` is checked against it. Pass `max_age` this way, not in `$parameters`, which refuses it. `$parameters` takes further authorization parameters such as `prompt` or `login_hint`; it refuses the ones the client sets itself and, since they are not supported, `request`, `request_uri`, `response_mode` and `claims`.

### Refreshing tokens

```php
use Appsolutely\Sdk\Oidc\IdToken;

$stored = $_SESSION['appsolutely_tokens'];
$tokens = $client->oidc()->refresh(
    $stored['refresh_token'],
    // Rebuilds what your own server stored; it verifies nothing, so never pass request data here.
    IdToken::fromTrustedStorage($stored['id_token']),
);
$_SESSION['appsolutely_tokens'] = [
    'refresh_token' => $tokens->refreshToken ?? $stored['refresh_token'],
    'id_token' => $tokens->idToken->toArray(),
];
```

`refresh()` requires the ID token of the sign-in, or the one the previous refresh returned, and refuses a refreshed ID token for another member or another authentication (OpenID Connect Core section 12.2). Its result always holds the ID token to keep for the next refresh: the refreshed one, or the original when the provider sent none. A refreshed ID token without `auth_time` keeps the original's in `$idToken->authTime`, so the next refresh is still compared against it. The stored array holds the raw ID token: keep it server-side with the refresh token, never in a cookie.

### Other calls

The same client reads the member's UserInfo claims (`userInfo($accessToken, $memberId)`) and revokes a token (`revoke()`). OAuth errors become `Exception\OAuthException` with the error code in `$exception->error`. Every exception an integrator may catch lives in the `Appsolutely\Sdk\Exception` namespace and implements `Exception\AppsolutelyException`. A refused argument is an `Exception\InvalidArgumentValueException`, or a subclass naming what was refused (`InvalidConfigException` for a `Config` value, `InvalidSecretException` for a webhook signing secret).

## Calling the Site API

`$client->api()` calls the site's API as its administrator, with the token from `Config`; `$client->backend()` calls it as the site's own backend, with a token its machine client is granted; `$client->forMember($accessToken)->api()` calls it as a signed-in member, with the access token their sign-in returned. Every operation of the Site API document has one typed method, on the client of the audience that may call it: the member's own data only on the member's, the backend's calls only on the backend's, everything else only on the administrator's.

Answers are small readonly models in `Appsolutely\Sdk\Model`. Ids are strings, times are `DateTimeImmutable` in UTC, money is an integer count of the currency's minor units beside its ISO 4217 `currency` (`1999` with `USD` is 19.99), and `$model->extra` keeps the members the site sent that the model has no property for, as decoded, so a field added by a newer site stays readable. A webhook delivery carries a record in the same shape as the API serves it, so typed events hold these same models. The document the client is written against is pinned at `Contract::REVISION`, a commit of the site software.

### Pages, articles and products

```php
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\ValidationFailedException;

$api = $client->api();

foreach ($api->articles()->list(sort: 'published_at', order: 'desc') as $article) {
    echo $article->title, ' ', $article->publishedAt->format(DATE_ATOM), "\n";
}

$article = $api->articles()->get($id);
$created = $api->articles()->create(['title' => 'Hello', 'content' => '<p>Hi</p>', 'published_at' => new DateTimeImmutable()]);
$created->value;          // the Article
$created->replayed;       // see "Writes that must happen once"

try {
    $api->articles()->update($id, ['title' => $title]);   // only the fields given change
} catch (ValidationFailedException $exception) {
    $errors = $exception->errors;          // ['title' => ['The title field is required.']]
} catch (NotFoundException) {
    // gone in the meantime
}

$page = $api->pages()->get($pageId);            // Model\ContentPage, so it is not mistaken for Api\Page
$product = $api->products()->get($productId);   // $product->prices: one ProductPrice per currency, the lowest of its skus
$product->skuPrices;                            // one ProductSkuPrice per sku and currency, with the interval a subscription sku renews in
```

### Orders, form entries, account states and webhook deliveries

```php
foreach ($api->orders()->list(sort: 'updated_at') as $order) {
    $order->totalAmount;      // minor units of $order->currency
    $order->items;            // list of OrderLine
}

$entry = $api->formEntries()->get($entryId);   // $entry->data holds the answers, keyed by field

$states = $api->accountStates()->list(['member-42', 'member-43']);   // one page, no cursor
foreach ($states->items as $state) {
    $state->entitlements;     // list of Entitlement
}

$failed = $api->webhookDeliveries()->list($subscription, status: 'failed', since: new DateTimeImmutable('-1 day'));
$api->webhookDeliveries()->redeliver($subscription, $webhookId);
```

### Sync feeds

A pull returns what changed in one resource since a cursor; keep `nextCursor` and pull from it next time, until `hasMore` is false:

```php
$cursor = $savedCursor;   // null the first time
do {
    $pull = $api->sync()->articles()->pull($cursor);
    foreach ($pull->upserts as $article) { /* created or changed */ }
    foreach ($pull->tombstones as $tombstone) { /* $tombstone->id was deleted */ }
    $cursor = $pull->nextCursor;
} while ($pull->hasMore);

$push = $api->sync()->articles()->push([
    ['mutation_id' => 'm-1', 'op' => 'upsert', 'id' => $id, 'client_updated_at' => $editedAt, 'attributes' => ['title' => 'Hi']],
]);
$push->results[0]->applied;   // and ->reason, ->errors, ->serverRecord
```

Feeds exist for articles, form entries, orders, pages and products. A `410` `cursor-expired` refusal means records went without a tombstone since the cursor: pull again from the start.

### The site's backend

The backend's token is asked for when first needed and kept in the cache from `Config` until a minute before it expires, so under PHP-FPM it is asked for once per token, not once per request. A token answered without `expires_in` is taken to live five minutes. When the site answers `invalid-token`, a new one is asked for and the call sent again, once. Asking for a token is retried like a read (after a network failure, a `429`, a `502`, a `503` or a `504`).

So that the processes sharing the cache do not all find the token due at the same moment, each read in the minute before it is due replaces it at a random point. That read still holds a good token, so it asks once, without retrying or waiting for a lock, and sends the current token if asking fails; the one request is made in that call and takes at most `Config::$timeout`. A failed early request is logged at `info` (a lock that throws, at `warning`). To have only one of them ask, pass `Config::$tokenLock`, an `Appsolutely\Sdk\Cache\TokenLock` built on your framework's lock. Early, a process asks only when the lock is free (`runIfFree()`) and otherwise keeps its token; once the token has expired, the others wait for the holder (`run()`) and take the new token from the cache:

```php
use Appsolutely\Sdk\Cache\TokenLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final class LaravelTokenLock implements TokenLock
{
    public function run(string $name, int $seconds, callable $fetch): mixed
    {
        try {
            return Cache::lock($name, $seconds)->block($seconds, $fetch);
        } catch (LockTimeoutException) {
            return $fetch();
        }
    }

    public function runIfFree(string $name, int $seconds, callable $fetch): ?string
    {
        $fetched = Cache::lock($name, $seconds)->get($fetch);

        return $fetched === false ? null : $fetched;
    }
}
```

```php
use Appsolutely\Sdk\Exception\ConflictException;

$backend = $client->backend();

$backend->client();                                           // the machine client: its mode, site, scopes and storeCurrency

$state = $backend->accountStates()->get($subject);           // AccountState::STATUS_UNKNOWN for a subject never handed out

// Reconcile by walks: each item is a page, and the walk's nextChangedSince
// is where the next one starts. Without $since it lists every account.
foreach ($backend->accountStates()->list($since) as $page) {
    foreach ($page->states as $listed) { /* $listed->state is the AccountState a delivery carries */ }
    $since = $page->nextChangedSince;                        // keep it for the next walk
}

$backend->products()->get($productId)->skuPrices;            // the catalogue, with the catalogue:read scope

try {
    $checkout = $backend->checkoutSessions()->create($subject, [['product' => 'team', 'quantity' => 5, 'sku' => 'team-yearly']], $successUrl, $cancelUrl);
    $checkout->value->url;                                   // send the member here
} catch (ConflictException $exception) {
    if ($exception->hasType('subscription-awaiting-payment')) {
        $exception->extension('payment_url');                // where the member pays the order already waiting, or null
    }
}
$backend->portalSessions()->create($subject, $returnUrl)->value->url;
$backend->returnTargets()->create('https://app.example.com/thanks');   // the addresses those may send the member back to

$intent = $backend->storePurchases()->intent($subject, 'app_store', 'team');
$backend->storePurchases()->transaction($subject, 'app_store', 'member', signedTransaction: $jws);

$backend->featureKeys()->create('reports.export', 'boolean');
$backend->featureValues()->set('team', 'reports.export', true);

$backend->roles()->create('editor');
$backend->roleAssignments()->assign($subject, 'editor');     // ->list(), ->get(), ->revoke()

$backend->seats()->grant($poolId, $payerSubject, $memberSubject);
$backend->seats()->pools($payerSubject);                     // the payer's pools and who holds each seat

$endpoint = $backend->webhookEndpoints()->create('https://app.example.com/hooks', ['order.paid', 'account.entitlements_changed']);
$endpoint->value->secret;                                    // sent this once: keep it for the verifier
$rotation = $backend->webhookEndpoints()->startRotation($endpoint->value->endpoint->id)->value;
$backend->webhookEndpoints()->completeRotation($rotation->endpoint->id, $rotation->rotationId);

$backend->deliveries()->list(status: 'failed');
$backend->deliveries()->redeliver($webhookId)->value->refused;   // the endpoints that refuse it now, and why

$backend->testClocks()->attach($subscriptionId);             // a subscription bought in test money only
$backend->testClocks()->advance($subscriptionId, 31 * 86400);
```

### The member's own data

```php
$member = $client->forMember($tokens->accessToken)->api();

$me = $member->me()->get();
$member->me()->update(name: 'Ada Lovelace');

foreach ($member->me()->addresses()->list() as $address) { /* Model\Address */ }
$filed = $member->me()->addresses()->create(['name' => 'Ada Lovelace', 'address' => '1 Main St']);
$member->me()->addresses()->update($addressId, ['city' => 'Leeds']);
$member->me()->addresses()->delete($addressId);

$member->me()->orders()->list();
$member->me()->entitlements()->list();                       // one page, no cursor
$member->me()->billingEntry()->get('app_store', storefront: 'GB');
$member->me()->pushDevices()->register($deviceToken, 'ios');
$member->me()->pushDevices()->unregister($deviceToken);
$member->me()->referral()->get();
$member->me()->referral()->rewards();

$member->me()->checkoutSessions()->create([['product' => 'team', 'quantity' => 1]], $successUrl, $cancelUrl);
$member->me()->portalSessions()->create($returnUrl);
$member->me()->storePurchases()->intent('google_play', 'team');
$member->me()->subscriptions()->list();                     // one page, no cursor
$member->me()->subscriptions()->change($subscriptionId, $returnUrl, quantity: 3)->value->applied;   // or ->confirmation, the visit that confirms it
$member->me()->subscriptions()->cancel($subscriptionId);
$member->me()->subscriptions()->resume($subscriptionId, $returnUrl);   // takes a cancellation back

$member->sync()->addresses()->pull($cursor);                 // and ->push(), and ->orders()->pull()
```

### Without a credential

The version record, the API document and the magic-link sign-in are open to anyone, so the client sends them without a credential even when it holds the administrator token:

```php
use Appsolutely\Sdk\Exception\ForbiddenException;
use Appsolutely\Sdk\Exception\RateLimitedException;

$version = $client->api()->version();         // ->status, ->deprecatedAt, ->sunsetAt, ->successor
$document = $client->api()->openApiDocument();

$client->api()->magicLink()->request($email, ['me:read', 'me:write'], 'Ada\'s phone', $codeChallenge);
try {
    $token = $client->api()->magicLink()->exchange($link, $codeVerifier, code: $code);
    $member = $client->forMember($token->token);
} catch (ForbiddenException $exception) {
    // second-factor-required: ask for the member's code and exchange the same link again
} catch (RateLimitedException $exception) {
    // rate-limited after too many codes: ask again in $exception->retryAfter seconds
}
```

### Lists, answers and refusals

A list method returns an `Api\Paginator` and sends no request until it is used. Iterating it walks every item, asking for each next page only when iteration reaches it and stopping at the page without a cursor; `page()` fetches one page, the first or the one a cursor kept from an earlier run asks for:

```php
$page = $client->api()->orders()->list(limit: 100)->page($savedCursor);
$page->items;                           // list of Model\Order
$savedCursor = $page->nextCursor;       // null on the last page
```

`limit` runs from 1 to 100 (25 by default). The cursor is sealed by the site, so pass back `nextCursor` unchanged and never build one. `$paginator->pages()` yields whole `Api\Page`s, and `$page->response` is the answer that carried the page.

Every call sends `Accept: application/json, application/problem+json`, the SDK's `User-Agent` and an `X-Request-Id`. An answer that breaks the document, such as a required field missing or a time without an offset, is an `Exception\UnexpectedResponseException` naming the schema and the field, never a value read as something else.

A refusal is thrown as an `Exception\ApiException` built from the site's RFC 9457 problem: `$type` (branch on it, or on `hasType('validation-failed')`), `$title`, `$status`, `$detail`, `$errors`, `$requestId`, `$retryAfter`, `$challenge` (the `WWW-Authenticate` header) and any other member through `extension('required_ability')`. `BadRequestException` (400), `ValidationFailedException`, `NotFoundException`, `UnauthenticatedException`, `ForbiddenException`, `ConflictException`, `RateLimitedException` and `ServerErrorException` (any 5xx, with `ServiceUnavailableException` for a 503 beneath it) extend it for the cases you are likely to handle, so catching `ApiException` still catches them all; an answer that is not a problem (a proxy's error page) keeps `$isProblem` false and the raw `$body`. A request that never got an answer is an `Exception\TransportException`; `isNetworkFailure()` says whether the network failed, the only case a retry can change, or the HTTP client refused the request. The network failed when the client throws PSR-18's `NetworkExceptionInterface`, or Guzzle's cURL transfer broke in transit before an answer (cURL error 55, 56 or 92, which Guzzle reports as a `RequestException` without a response); any other cURL error, a certificate one included, is not retried.

### Untyped calls

Each client's `raw()` gives the calls underneath the typed methods, with the same credential, headers, retries and refusals, for an operation the site has and this client was not written against. Paths are the ones the site's API document names; a success is an `Api\ApiResponse`: the decoded body in `$data` (null for a `204`), `$status`, `$location` for a created resource, `$requestId` as the site answered it, and `$rateLimit`, the budget the site states in its `RateLimit-Policy` and `RateLimit` headers, by quota name (`$response->rateLimit->quota('api:authenticated')?->remaining`):

```php
$data = $client->api()->raw()->get('/api/v1/articles/' . rawurlencode($id), requestId: $traceId)->data;

foreach ($client->api()->raw()->paginate('/api/v1/orders', ['sort' => 'updated_at', 'order' => 'desc'], limit: 100) as $order) {
    // every order as decoded JSON, most recently changed first, 100 per request
}
```

### Writes that must happen once

Every write the site keys accepts an `Idempotency-Key`, and its method always sends one: creating an article, filing a member's address, redelivering a webhook, starting a checkout or a visit to the billing pages, cancelling, changing or resuming a member's subscription, attaching or advancing a test clock, and creating a webhook endpoint or starting its secret rotation. The key is yours (1 to 255 printable ASCII characters, spaces included, one per operation) or a fresh UUID v4, sent as a quoted Structured Field String (RFC 9651) as the Idempotency-Key draft defines it. The same key goes with every retry, so a retry after a lost answer is answered with the first answer, its status and body, instead of acting a second time (a replayed billing visit carries a fresh single-use `url` for the same visit), and the result says which key was sent and whether the answer was replayed:

```php
$filed = $member->me()->addresses()->create($address, idempotencyKey: $formSubmissionId);
$filed->value;            // the Address
$filed->replayed;         // true when this is the stored answer to an earlier attempt
$filed->idempotencyKey;   // the key that was sent; send it again to retry the same write
```

Untyped, the same is `raw()->postIdempotent($path, $body, $key)`.

Reads and keyed writes are retried after a network failure, a `429`, a `502`, a `503` or a `504`, a read also after a `500`, and, for a keyed write, the `409` of an attempt with the same key still running. Each wait is what the site asks in `Retry-After` (or, on a `429` without it, until the spent quota in `RateLimit` turns over), otherwise a jittered backoff doubling from half a second. Other refusals are thrown at once, and so is a `500` under a key, whose outcome only reading the current state can settle; send the operation again under a new key if it still needs doing. Other writes (`update()`, `delete()`, a sync push, and `raw()`'s `post()`, `put()`, `patch()` and `delete()`) are never retried. Tune or turn this off in `Config`:

```php
use Appsolutely\Sdk\Api\RetryPolicy;

new Config(/* ... */, retryPolicy: new RetryPolicy(maxRetries: 3, maxDelay: 60));
new Config(/* ... */, retryPolicy: RetryPolicy::none());
```

`maxRetries` runs from 0 to 10 (2 by default), and a wait the site asks for beyond `maxDelay` seconds (30 by default) is not made: the refusal is thrown instead. Through the PSR-3 logger, each attempt is logged at `debug` with its method, path, status, request id and attempt number, each retry at `info`, and giving up on a network failure, a `5xx` or a refusal a retry could have passed at `warning`, as is a failed request for the backend's token. Headers, bodies, query strings, tokens and keys are never logged.

When the site refuses a token with `UnauthenticatedException`, renew it rather than retrying: refused credentials count against a budget of their own, and a client that keeps sending a dead token locks out every caller behind the same address.

### Testing without a site

`Testing\FakeClient` answers the Site API in memory. Arrange an answer per operation, named by `Api\Operation`, hand `client()` to the code under test, then read the calls it made:

```php
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;

$fake = (new FakeClient())
    ->answer(Operation::GetArticle, ['id' => 'a-1', 'title' => 'Hello', /* ... as the document shapes it */])
    ->answerPage(Operation::ListOrders, [$order1, $order2], nextCursor: 'c2')
    ->answerPage(Operation::ListOrders, [$order3])
    ->refuse(Operation::CreateArticle, 'validation-failed', 422, 'The request did not pass validation.', members: ['errors' => ['title' => ['Required.']]]);

$service = new ArticleImporter($fake->client());   // a real Client; nothing leaves the process
$service->run();

$call = $fake->lastCall(Operation::CreateArticle);
$call->body;              // the JSON body sent, decoded
$call->idempotencyKey;    // the key sent with a keyed write
$call->pathParameters;    // ['id' => 'a-1'] for a path with placeholders
$call->query;             // the query, decoded
$call->token;             // FakeClient::ADMINISTRATOR_TOKEN, a member's token, or null
$fake->calls();           // every call, in order
```

`client()` is a real `Client` whose HTTP client answers from the arrangement, so an arranged body is read into the same models as a site's answer, a refusal becomes the same `ApiException` subclass, and an answer that breaks the document fails the same way. Answers to one operation are given in the order arranged, and the last one keeps answering. `answer()` takes the operation's success status unless you pass another, and headers such as `Idempotent-Replayed: true`; `refuse()` takes a problem type (`validation-failed`, or a full URI), a status, a title, an optional detail (left out when empty, as the site leaves it out), and the problem's extension members, which may not reuse a standard member (`type`, `title`, `status`, `detail`, `instance`); a `401` carries the site's `WWW-Authenticate: Bearer` challenge, and an `insufficient-scope` `403` the challenge naming its `required` scope, unless you pass another. `forMember($token)` calls as a member. Calls are not retried, so a refusal is thrown at once. A call with no answer arranged, or to a path that is no operation of the document, throws `Exception\UnarrangedCallException`, a `LogicException`. Sign-in through `oidc()` is not faked.

## Verifying webhook deliveries

Deliveries follow [Standard Webhooks](https://www.standardwebhooks.com/). Verify the raw request body, before any framework parses it:

```php
use Appsolutely\Sdk\Webhooks\Deliveries;
use Appsolutely\Sdk\Webhooks\Verifier;
use Appsolutely\Sdk\Exception\WebhookVerificationException;

$secret = getenv('APPSOLUTELY_WEBHOOK_SECRET');
if ($secret === false) {
    throw new RuntimeException('Set APPSOLUTELY_WEBHOOK_SECRET.');
}
$verifier = new Verifier([$secret]); // add the old secret too while rotating
$deliveries = new Deliveries($psr16Cache);

// An unreadable body becomes '', which fails verification like any other.
$body = (string) file_get_contents('php://input');

try {
    $event = $verifier->verify($body, getallheaders());
} catch (WebhookVerificationException) {
    http_response_code(400);
    exit;
}

if (!$deliveries->wasHandled($event->id)) {
    handle($event);                     // $event->type, $event->data, $event->mode, ...
    $deliveries->markHandled($event->id);
}
http_response_code(204);
```

A PSR-7 server request can be passed to `$verifier->verifyRequest($request)` instead. When several endpoints share one cache (two apps subscribed to the same events, say), give each its own namespace, `new Deliveries($psr16Cache, namespace: 'orders')`, or one would skip a delivery the other handled. A retried or redelivered event keeps its id, so mark an id only after your handler has succeeded and answer 2xx for one already handled.

Answer with a 2xx within a few seconds and queue slow work: an attempt that times out counts as failed and is retried. Never redirect the endpoint: redirects are not followed, so a 3xx is a failed attempt. Answering `410 Gone` switches the endpoint off.

### Typed events

`TypedEvent::from()` reads a verified envelope's `data` into typed, read-only properties: one class per family of event types that share a payload. The package's contract tests hold every family to the payload schema the site's API document gives each event type in its `webhooks` section, and `EventType` to the types it lists.

```php
use Appsolutely\Sdk\Webhooks\EventType;
use Appsolutely\Sdk\Webhooks\Events\AccountEvent;
use Appsolutely\Sdk\Webhooks\Events\OrderPaidEvent;
use Appsolutely\Sdk\Webhooks\Events\TypedEvent;
use Appsolutely\Sdk\Webhooks\Events\UnknownEvent;

$typed = TypedEvent::from($event);         // $event is what verify() returned

match (true) {
    $typed instanceof OrderPaidEvent => bookPurchase($typed->payment?->reference, $typed->order, $typed->subject),
    $typed instanceof AccountEvent => applyState($typed->state),          // keep the highest sequence per subject
    $typed instanceof UnknownEvent => null,                              // a type this client does not know yet
    default => null,
};
```

| Class | Types | Properties |
| --- | --- | --- |
| `ArticleEvent`, `PageEvent`, `ProductEvent` | `article.*`, `page.*`, `product.*` (`created`, `updated`, `deleted`) | `$article`, `$page`, `$product` |
| `FormSubmittedEvent` | `form.submitted` | `$entry`, `$formName` |
| `OrderEvent` | `order.completed`, `order.shipped`, `order.cancelled`, `order.status_updated`, `order.expired`, `order.revived`, `order.payment_arrived_late` | `$order` |
| `OrderPaidEvent` | `order.paid` | `$order`, `$payment`, `$subject` |
| `PaymentReturnedEvent` | `order.refunded`, `payment.refunded`, `payment.reversed` | `$order`, `$payment` |
| `RefundEvent` | `refund.requested`, `refund.processed` | `$refund`, a `Model\Refund`: its order number, the payment's reference, the amount and its `status` |
| `ReferralRewardIssuedEvent` | `referral.reward_issued` | `$subject`, `$referralCode`, `$paymentReference`, `$reward` |
| `SubscriptionEvent` | `subscription.*` | `$subscription`, and `$period`: always on `renewed`, on `started` when the start opened one, null otherwise |
| `AccountEvent` | `account.suspended`, `account.reinstated`, `account.erased`, `account.entitlements_changed`, `account.email_changed` | `$state` |
| `RoleAssignmentChangedEvent` | `role.assignment_changed` | `$subject`, `$role`, `$change`, `$cause`, `$roles`, `$sequence` |
| `PingEvent` | `webhook.ping` | `$subscriptionId` |
| `RotationEndingEvent` | `webhook.rotation_ending` | `$endpointId`, `$rotationStartedAt`, `$completesAt` |
| `UnknownEvent` | any other type | none |

`EventType` names every type as a constant. Times are `DateTimeImmutable` in UTC, ids are strings, and amounts are integers in the minor unit of the `currency` beside them. The records they carry are the `Model` classes the API returns (an `ArticleEvent`'s `$article` is a `Model\Article`), and every model keeps the members the site sent that it has no property for in `$extra`, and every event keeps its envelope in `$envelope`, `data` included, so nothing that arrived is out of reach.

A type this client does not know becomes an `UnknownEvent`, never an exception: acknowledge it with a 2xx. A known type whose `data` lacks a field or carries one of the wrong type throws `Exception\UnexpectedPayloadException`; the delivery is genuine, so answer it with a 5xx and the site will retry it.

An account's `totals` is an array of integers by entitlement key, empty when the account holds nothing (the site sends `{}`). Decide what to do from `$state->status` and apply a state only when its `$state->sequence` is higher than the one you hold for that `$state->subject`: deliveries can arrive out of order, and changes close together can arrive as fewer deliveries.

### Testing your endpoint

The classes in `Appsolutely\Sdk\Testing` are test helpers: use them in your test suite, not in production code. `Testing\WebhookFactory` builds deliveries signed exactly as Appsolutely signs them:

```php
use Appsolutely\Sdk\Testing\WebhookFactory;
use Appsolutely\Sdk\Webhooks\EventType;

$delivery = (new WebhookFactory(['whsec_...']))->make(EventType::ROLE_ASSIGNMENT_CHANGED, [
    'subject' => 'member-sub', 'role' => 'editor', 'change' => 'added', 'cause' => 'app', 'roles' => ['editor'], 'sequence' => 7,
]);
// $delivery->body and $delivery->headers are what your endpoint receives.
```

## Versioning

The package follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html), and every change is listed in the [CHANGELOG](CHANGELOG.md).

- **Before 1.0**, the public API may still change: a breaking change raises the minor version (`0.1` to `0.2`) and is marked as breaking in the CHANGELOG, while a patch release (`0.1.1`) never breaks. A `^0.1` constraint therefore never takes a breaking release.
- **From 1.0**, a breaking change waits for the next major version.
- **Deprecations**: a method, class or option to be removed is first marked `@deprecated` with its replacement and listed under *Deprecated* in the CHANGELOG, and stays for at least one minor release before it is removed in a release that may break (the next minor before 1.0, the next major after it).

Everything marked `@internal` is outside the public API and may change in any release.

## Security

Report a vulnerability privately, as [SECURITY.md](SECURITY.md) describes, never in a public issue.

## License

MIT — see [LICENSE](LICENSE).

# Appsolutely PHP SDK

A framework-free PHP client for one app's own site: it signs the site's members in with OpenID Connect, calls the Site API as the site's administrator or as a signed-in member, and verifies the site's Standard Webhooks deliveries.

A site hosted for you and one you host yourself answer the same paths in the same shapes, so pointing the client from one to the other changes the base URL, the issuer and the credentials, nothing else.

Laravel applications install the bridge, [`appsolutely/sdk-laravel`](https://github.com/appsolutely-io/sdk-laravel), which wires this client into the container, Socialite and the router.

## Requirements

- PHP 8.3 or newer
- A PSR-18 HTTP client (any installed one is discovered)

## Installation

The repository is private for now, so Composer installs it from GitHub with a token that can read it:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/appsolutely-io/sdk-php" }
    ]
}
```

There is no tagged release yet, so name the development line explicitly; an application at the default `minimum-stability: stable` refuses it otherwise:

```bash
composer require "appsolutely/sdk-php:^0.1@dev"
```

The constraint keeps working when `0.1.0` is tagged; drop `@dev` then.

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

$client = new Client(new Config(
    baseUrl: 'https://shop.example.com',           // the site's origin, without a path
    issuer: 'https://shop.example.com',            // the site's OpenID issuer, exactly as its discovery names it
    clientId: $clientId,                           // the site's OAuth client that signs members in
    clientSecret: $clientSecret,
    apiToken: $apiToken,                           // optional: the administrator token issued on the site
    cache: $psr16Cache,                            // your application's shared cache; see below
));
```

The administrator token is a long-lived credential issued on the site's API-token screen; it is sent on the calls your server makes on its own behalf. Leave it out when your server only acts for signed-in members. Keep it, like the client secret, out of version control.

`Config` also takes a PSR-18 client and PSR-17 factories, a PSR-20 clock, a PSR-3 logger, the token-endpoint authentication (`client_secret_basic` by default, or `ClientAuthentication::ClientSecretPost`), the clock leeway for ID tokens (60 seconds by default) and the retry policy for Site API calls (see [Writes that must happen once](#writes-that-must-happen-once)).

### The cache

In production, pass your application's shared PSR-16 cache (Redis, Memcached, APCu or your framework's cache). Without one, the client falls back to a cache in memory that lives as long as the PHP process; under PHP-FPM that is a single request, so every request fetches the discovery document and the signing keys again. The fallback exists so the client works anywhere, not for production.

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

`$client->api()` calls the site's API as its administrator, with the token from `Config`; `$client->forMember($accessToken)->api()` calls it as a signed-in member, with the access token their sign-in returned. Every operation of the Site API document has one typed method, on the client of the audience that may call it: the member's own data only on the member's, everything else only on the administrator's.

Answers are small readonly models in `Appsolutely\Sdk\Model`. Ids are strings, times are `DateTimeImmutable` in UTC, money is an integer count of the currency's minor units beside its ISO 4217 `currency` (`1999` with `USD` is 19.99), and `$model->attributes` keeps the whole object as the site sent it, so a field added by a newer site stays readable. The document the client is written against is pinned at `Contract::REVISION`, a commit of the site software.

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
$product = $api->products()->get($productId);   // $product->prices: one ProductPrice per currency
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

$member->sync()->addresses()->pull($cursor);                 // and ->push(), and ->orders()->pull()
```

### Without a credential

The version record, the API document and the magic-link sign-in are open to anyone, so the client sends them without a credential even when it holds the administrator token:

```php
$version = $client->api()->version();         // ->status, ->deprecation, ->sunset, ->successor
$document = $client->api()->openApiDocument();

$client->api()->magicLink()->request($email, ['me:read', 'me:write'], 'Ada\'s phone', $codeChallenge);
$token = $client->api()->magicLink()->exchange($link, $codeVerifier);
$member = $client->forMember($token->token);
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

A refusal is thrown as an `Exception\ApiException` built from the site's RFC 9457 problem: `$type` (branch on it, or on `hasType('validation-failed')`), `$title`, `$status`, `$detail`, `$errors`, `$requestId`, `$retryAfter`, `$challenge` (the `WWW-Authenticate` header) and any other member through `extension('required_ability')`. `ValidationFailedException`, `NotFoundException`, `UnauthenticatedException`, `ForbiddenException`, `ConflictException`, `RateLimitedException` and `ServiceUnavailableException` extend it for the cases you are likely to handle; an answer that is not a problem (a proxy's error page) is an `ApiException` with `$isProblem` false and the raw `$body`. A request that never got an answer is an `Exception\TransportException`.

### Untyped calls

Each client's `raw()` gives the calls underneath the typed methods, with the same credential, headers, retries and refusals, for an operation the site has and this client was not written against. Paths are the ones the site's API document names; a success is an `Api\ApiResponse`: the decoded body in `$data` (null for a `204`), `$status`, `$location` for a created resource, `$requestId` as the site answered it, and `$rateLimit`, the budget the site states in its `RateLimit-Policy` and `RateLimit` headers, by quota name (`$response->rateLimit->quota('api:authenticated')?->remaining`):

```php
$data = $client->api()->raw()->get('/api/v1/articles/' . rawurlencode($id), requestId: $traceId)->data;

foreach ($client->api()->raw()->paginate('/api/v1/orders', ['status' => 'paid'], limit: 100) as $order) {
    // every paid order as decoded JSON, 100 per request
}
```

### Writes that must happen once

Creating an article, filing a member's address and redelivering a webhook accept an `Idempotency-Key`, and their methods always send one: yours (1 to 255 printable ASCII characters, one per operation) or a fresh UUID v4. The same key goes with every retry, so a retry after a lost answer is answered with the first answer instead of creating a second record, and the result says which key was sent and whether the answer was replayed:

```php
$filed = $member->me()->addresses()->create($address, idempotencyKey: $formSubmissionId);
$filed->value;            // the Address
$filed->replayed;         // true when this is the stored answer to an earlier attempt
$filed->idempotencyKey;   // the key that was sent; send it again to retry the same write
```

Untyped, the same is `raw()->postIdempotent($path, $body, $key)`.

Reads and keyed writes are retried after a network failure, a `429`, a `503`, and, for a keyed write, the `409` of an attempt with the same key still running. Each wait is what the site asks in `Retry-After` (or, on a `429` without it, until the spent quota in `RateLimit` turns over), otherwise a jittered backoff doubling from half a second. Other refusals are thrown at once, and so is a `500` under a key, whose outcome only reading the current state can settle; send the operation again under a new key if it still needs doing. Other writes (`update()`, `delete()`, a sync push, and `raw()`'s `post()`, `put()`, `patch()` and `delete()`) are never retried. Tune or turn this off in `Config`:

```php
use Appsolutely\Sdk\Api\RetryPolicy;

new Config(/* ... */, retryPolicy: new RetryPolicy(maxRetries: 3, maxDelay: 60));
new Config(/* ... */, retryPolicy: RetryPolicy::none());
```

`maxRetries` runs from 0 to 10 (2 by default), and a wait the site asks for beyond `maxDelay` seconds (30 by default) is not made: the refusal is thrown instead. Each retry is logged at `info` through the PSR-3 logger.

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
    ->refuse(Operation::CreateArticle, 'validation-failed', 422, members: ['errors' => ['title' => ['Required.']]]);

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

`client()` is a real `Client` whose HTTP client answers from the arrangement, so an arranged body is read into the same models as a site's answer, a refusal becomes the same `ApiException` subclass, and an answer that breaks the document fails the same way. Answers to one operation are given in the order arranged, and the last one keeps answering. `answer()` takes the operation's success status unless you pass another, and headers such as `Idempotent-Replayed: true`; `refuse()` takes a problem type (`validation-failed`, or a full URI), a status, and the problem's other members. `forMember($token)` calls as a member. Calls are not retried, so a refusal is thrown at once. A call with no answer arranged, or to a path that is no operation of the document, throws `Exception\UnarrangedCallException`, a `LogicException`. Sign-in through `oidc()` is not faked.

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

### Testing your endpoint

The classes in `Appsolutely\Sdk\Testing` are test helpers: use them in your test suite, not in production code. `Testing\WebhookFactory` builds deliveries signed exactly as Appsolutely signs them:

```php
use Appsolutely\Sdk\Testing\WebhookFactory;

$delivery = (new WebhookFactory(['whsec_...']))->make('entitlement.granted', ['member' => 'm_1']);
// $delivery->body and $delivery->headers are what your endpoint receives.
```

## License

MIT — see [LICENSE](LICENSE).

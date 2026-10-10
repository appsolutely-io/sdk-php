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

`$client->api()` calls the site's API as its administrator, with the token from `Config`; `$client->forMember($accessToken)->api()` calls it as a signed-in member, with the access token their sign-in returned. Each has `raw()`, the untyped calls underneath, for an operation the site has and this client was not written against; paths are the ones the site's API document names:

```php
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\ValidationFailedException;

$article = $client->api()->raw()->get('/api/v1/articles/' . rawurlencode($id))->data;

$me = $client->forMember($tokens->accessToken)->api()->raw()->get('/api/v1/me')->data;

try {
    $client->api()->raw()->patch('/api/v1/articles/' . rawurlencode($id), ['title' => $title]);
} catch (ValidationFailedException $exception) {
    $errors = $exception->errors;          // ['title' => ['The title field is required.']]
} catch (NotFoundException) {
    // gone in the meantime
}
```

Every call sends `Accept: application/json, application/problem+json`, the SDK's `User-Agent` and an `X-Request-Id`: a fresh UUID, or the one you pass as `requestId:` to tie the call to your own logs. A success is an `Api\ApiResponse`: the decoded resource in `$data` (null for a `204`), `$status`, `$location` for a created resource, `$requestId` as the site answered it, and `$rateLimit`, the budget the site states in its `RateLimit-Policy` and `RateLimit` headers, by quota name (`$response->rateLimit->quota('api:authenticated')?->remaining`).

A list answers a page, `{"data": [...], "next_cursor": "..."}`. `paginate()` walks every item, asking for each next page only when iteration reaches it and stopping at the page without a cursor; `page()` fetches one page when you keep the cursor yourself, say between requests:

```php
foreach ($client->api()->raw()->paginate('/api/v1/orders', ['status' => 'paid'], limit: 100) as $order) {
    // every paid order, 100 per request
}

$page = $client->api()->raw()->page('/api/v1/orders', ['status' => 'paid'], cursor: $savedCursor);
$savedCursor = $page->nextCursor;      // null on the last page
```

`limit` runs from 1 to 100 (25 by default). The filters go with every page; the cursor is sealed by the site, so pass back `nextCursor` unchanged and never build one. `$paginator->pages()` yields whole `Api\Page`s, and `$paginator->map($fn)` converts each item as it is reached.

A refusal is thrown as an `Exception\ApiException` built from the site's RFC 9457 problem: `$type` (branch on it, or on `hasType('validation-failed')`), `$title`, `$status`, `$detail`, `$errors`, `$requestId`, `$retryAfter`, `$challenge` (the `WWW-Authenticate` header) and any other member through `extension('required_ability')`. `ValidationFailedException`, `NotFoundException`, `UnauthenticatedException`, `ForbiddenException`, `ConflictException`, `RateLimitedException` and `ServiceUnavailableException` extend it for the cases you are likely to handle; an answer that is not a problem (a proxy's error page) is an `ApiException` with `$isProblem` false and the raw `$body`. A request that never got an answer is an `Exception\TransportException`.

### Writes that must happen once

Some writes, such as creating an article or filing a member's address, accept an `Idempotency-Key`. Send them with `postIdempotent()`: the client generates a UUID v4 key (or takes yours, 1 to 255 printable ASCII characters, one per operation) and sends the same key on every retry, so a retry after a lost answer is answered with the first answer instead of creating a second record:

```php
$response = $client->forMember($accessToken)->api()->raw()->postIdempotent('/api/v1/me/addresses', $address);
$response->replayed;          // true when this is the stored answer to an earlier attempt
$response->idempotencyKey;    // the key that was sent
```

Reads and keyed writes are retried after a network failure, a `429`, a `503`, and, for a keyed write, the `409` of an attempt with the same key still running. Each wait is what the site asks in `Retry-After` (or, on a `429` without it, until the spent quota in `RateLimit` turns over), otherwise a jittered backoff doubling from half a second. Other refusals are thrown at once, and so is a `500` under a key, whose outcome only reading the current state can settle; send the operation again under a new key if it still needs doing. `post()`, `put()`, `patch()` and `delete()` are never retried. Tune or turn this off in `Config`:

```php
use Appsolutely\Sdk\Api\RetryPolicy;

new Config(/* ... */, retryPolicy: new RetryPolicy(maxRetries: 3, maxDelay: 60));
new Config(/* ... */, retryPolicy: RetryPolicy::none());
```

`maxRetries` runs from 0 to 10 (2 by default), and a wait the site asks for beyond `maxDelay` seconds (30 by default) is not made: the refusal is thrown instead. Each retry is logged at `info` through the PSR-3 logger.

When the site refuses a token with `UnauthenticatedException`, renew it rather than retrying: refused credentials count against a budget of their own, and a client that keeps sending a dead token locks out every caller behind the same address.

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

# Appsolutely PHP SDK

A framework-free PHP client for relying parties of Appsolutely: OpenID Connect sign-in, the machine token for the party's own API calls (the calls themselves are not wrapped yet), and verified Standard Webhooks deliveries.

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
if ($clientId === false || $clientSecret === false) {
    throw new RuntimeException('Set APPSOLUTELY_CLIENT_ID and APPSOLUTELY_CLIENT_SECRET.');
}

$client = new Client(new Config(
    issuer: 'https://login.example.com',          // the party's sign-in host, exactly as discovery names it
    clientId: $clientId,
    clientSecret: $clientSecret,
    cache: $psr16Cache,                            // your application's shared cache; see below
));
```

`Config` also takes a PSR-18 client and PSR-17 factories, a PSR-20 clock, a PSR-3 logger, the token-endpoint authentication (`client_secret_basic` by default, or `ClientAuthentication::ClientSecretPost`) and the clock leeway for ID tokens (60 seconds by default).

### The cache

In production, pass your application's shared PSR-16 cache (Redis, Memcached, APCu or your framework's cache). Without one, the client falls back to a cache in memory that lives as long as the PHP process; under PHP-FPM that is a single request, so every request fetches the discovery document and the signing keys again and asks for a new machine token. The fallback exists so the client works anywhere, not for production.

The cache holds the machine tokens from `machineToken()` until shortly before they expire. They are live bearer credentials for your party's API calls: protect the cache like a credential store, and do not share it with applications that should not act as your party.

## Signing a member in with OpenID Connect

Two requests: one sends the member to Appsolutely, the other receives them back.

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

The same client reads the member's UserInfo claims (`userInfo($accessToken, $memberId)`), revokes a token (`revoke()`) and obtains a cached `client_credentials` token for the party's own API calls (`machineToken()`). OAuth errors become `Exception\OAuthException` with the error code in `$exception->error`. Every exception an integrator may catch lives in the `Appsolutely\Sdk\Exception` namespace and implements `Exception\AppsolutelyException`. A refused argument is an `Exception\InvalidArgumentValueException`, or a subclass naming what was refused (`InvalidConfigException` for a `Config` value, `InvalidSecretException` for a webhook signing secret).

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

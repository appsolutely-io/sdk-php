# Appsolutely PHP SDK

A framework-free PHP client for relying parties of Appsolutely: OpenID Connect sign-in, the party's own API calls, and verified Standard Webhooks deliveries.

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

```bash
composer require appsolutely/sdk-php
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

$client = new Client(new Config(
    issuer: 'https://login.example.com',          // the party's sign-in host, exactly as discovery names it
    apiBaseUrl: 'https://api.appsolutely.io/api/relying-party/v1',
    clientId: getenv('APPSOLUTELY_CLIENT_ID'),
    clientSecret: getenv('APPSOLUTELY_CLIENT_SECRET'),
    cache: $psr16Cache,                            // strongly recommended: discovery, keys and machine tokens live here
));
```

`Config` also takes a PSR-18 client and PSR-17 factories, a PSR-20 clock, a PSR-3 logger, the token-endpoint authentication (`client_secret_basic` by default, or `ClientAuthentication::ClientSecretPost`) and the clock leeway for ID tokens (60 seconds by default). Without a cache the client keeps one in memory for the life of the process, which under PHP-FPM is a single request.

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
```

`authorizationUrl()` generates `state`, `nonce` and a PKCE S256 verifier. `exchangeCallback()` checks the `iss` and `state` of the response, exchanges the code with the verifier and verifies the ID token: its signature against the provider's published keys (RS256 or ES256), `iss`, `aud`, `azp`, `exp`, `iat` and the `nonce`. Any failure throws; never sign the member in after an exception.

The same client refreshes tokens (`refresh()`), reads userinfo (`userInfo($accessToken, $memberId)`), revokes a token (`revoke()`) and obtains a cached `client_credentials` token for the party's own API calls (`machineToken()`). OAuth errors become `Oidc\OAuthException` with the error code in `$exception->error`; every exception the package throws implements `Exception\AppsolutelyException`.

## Verifying webhook deliveries

Deliveries follow [Standard Webhooks](https://www.standardwebhooks.com/). Verify the raw request body, before any framework parses it:

```php
use Appsolutely\Sdk\Webhooks\Deliveries;
use Appsolutely\Sdk\Webhooks\Verifier;
use Appsolutely\Sdk\Webhooks\WebhookVerificationException;

$verifier = new Verifier([getenv('APPSOLUTELY_WEBHOOK_SECRET')]); // add the old secret too while rotating
$deliveries = new Deliveries($psr16Cache);

try {
    $event = $verifier->verify(file_get_contents('php://input'), getallheaders());
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

A PSR-7 server request can be passed to `$verifier->verifyRequest($request)` instead. A retried or redelivered event keeps its id, so mark an id only after your handler has succeeded and answer 2xx for one already handled.

Answer with a 2xx within a few seconds and queue slow work: an attempt that times out counts as failed and is retried. Never redirect the endpoint: redirects are not followed, so a 3xx is a failed attempt. Answering `410 Gone` switches the endpoint off.

### Testing your endpoint

`Testing\WebhookFactory` builds deliveries signed exactly as Appsolutely signs them:

```php
use Appsolutely\Sdk\Testing\WebhookFactory;

$delivery = (new WebhookFactory(['whsec_...']))->make('entitlement.granted', ['member' => 'm_1']);
// $delivery->body and $delivery->headers are what your endpoint receives.
```

## License

MIT — see [LICENSE](LICENSE).

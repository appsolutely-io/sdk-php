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

## License

MIT — see [LICENSE](LICENSE).

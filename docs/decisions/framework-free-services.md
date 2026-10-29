# Framework-free service interfaces

## Decision

Use PSR HTTP interfaces, a PSR simple cache, logger and clock, with optional discovery of an installed HTTP client and factories. Keep framework-specific integration in consuming applications.

## Rationale

An application can retain its HTTP stack instead of installing a mandatory Guzzle client. Discovery supplies convenience without making a particular transport a runtime requirement. Discovery documents, signing keys, machine tokens and handled delivery ids need key/value entries with expiry; PSR simple cache fits that use and the caches frameworks expose. An injectable clock makes authentication and webhook checks agree with the application's time.

## Alternatives and costs

Requiring a framework excludes plain PHP applications. Requiring one HTTP implementation replaces an application's existing choice. A PSR cache pool adds an adapter where the required operation is a key with a lifetime. These interfaces cost the SDK discovery and adapter code; the integrator must install a compatible HTTP implementation. Timeout settings cannot be expressed portably through the HTTP interface, so supplied clients retain their own settings.

## Current implementation

[`composer.json`](../../composer.json) declares the interfaces and discovery package. [`HttpTransport::forConfig()`](../../src/Http/HttpTransport.php) assembles supplied or discovered services; [`ClientTimeouts`](../../src/Http/ClientTimeouts.php) handles supported implementations. [`Config`](../../src/Config.php) carries cache, clock and logger injection. The [configuration guide](../../README.md#configuration) describes their use, including timeout limitations. The transport contract is [PSR-18](https://www.php-fig.org/psr/psr-18/).

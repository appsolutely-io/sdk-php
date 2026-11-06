# Refuse serialization of credential-bearing clients

## Decision

Redact debug dumps and refuse serialization of configuration, clients and webhook signing secrets. Mark sensitive inputs throughout the call path, including model readers.

## Rationale

Serializing an intact client carries credentials into a session, queue or cache; serializing a redacted client produces an object that cannot authenticate and fails away from its construction point. Rebuilding from settings is explicit, and the injected services are not portable serialized state either. Debug output uses an explicit list of fields so adding a property does not automatically make it visible.

Exception chains and frame arguments are another disclosure path. A sanitized message cannot remove a raw ID token captured in a JWT library exception's stack, so signature refusals do not chain that exception. JSON payload readers also receive personal data and need sensitive-parameter protection, even when their argument names do not sound like credentials.

## Alternatives and costs

Public secret properties, automatic object dumps and blanket exception chaining make diagnostics capable of exposing credentials. Redacted serialization hides a broken reconstructed object. The chosen design limits debugging and requires clients to be reconstructed rather than queued. It does not make arbitrary exports safe: `Config` explicitly warns that `var_export()` ignores its debug hook. Applications still protect their token storage.

## Current implementation

[`Config`](../../src/Config.php), [`Client`](../../src/Client.php), [`OpenIdClient`](../../src/Oidc/OpenIdClient.php) and [`Secret`](../../src/Webhooks/Secret.php) refuse serialization. [`AdminConfig`](../../src/Admin/AdminConfig.php) wraps its secret in `SensitiveParameterValue`. [`IdTokenVerifier::verifySignature()`](../../src/Oidc/IdTokenVerifier.php) avoids chaining token-bearing library exceptions. [`SensitiveParametersTest`](../../tests/SensitiveParametersTest.php) inspects methods and closures, while [`SecretExposureTest`](../../tests/Webhooks/SecretExposureTest.php) checks webhook objects.

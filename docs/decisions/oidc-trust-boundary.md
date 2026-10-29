# Restrict ID token trust to this client and asymmetric signing keys

## Decision

Accept only this client's audience and the intersection of the provider's advertised signing algorithms with RS256 and ES256. Keep signature verification and claim validation inside the SDK.

## Rationale

Accepting an advertised symmetric algorithm would let possession of the client secret become signing authority. An ID token that includes this client alongside another untrusted audience is also insufficient: this integration has no configured set of trusted additional audiences. Audience syntax is checked as a string or list of strings, preserving JSON object/list distinctions before accepting it.

The JWT library supplies signature and key parsing rather than the whole relying-party policy. Its static timestamp and leeway are set from the injected clock for one decode and restored in `finally`, so library checks and SDK checks use the same time without leaving changed global settings behind.

## Alternatives and costs

Trusting every advertised algorithm broadens who can mint an accepted token. Accepting any audience list containing the client silently trusts additional audiences. Supporting either requires an explicit trust design the configuration does not provide. The narrower choice rejects providers or tokens outside this contract. A larger JWT framework is unnecessary for the supported signing and key formats; the SDK instead owns the remaining validation and integration code.

## Current implementation

[`IdTokenVerifier`](../../src/Oidc/IdTokenVerifier.php) checks algorithms, audience shape and claims, and brackets library globals in `verifySignature()`. [`KeySet`](../../src/Oidc/KeySet.php) selects matching signing keys and excludes keys designated for encryption. [`IdTokenVerificationTest`](../../tests/Oidc/IdTokenVerificationTest.php) covers these refusals. The relying-party validation requirements are in [OpenID Connect Core, ID Token Validation](https://openid.net/specs/openid-connect-core-1_0.html#IDTokenValidation). The [sign-in guide](../../README.md#signing-a-member-in-with-openid-connect) covers the caller's flow; [refreshing tokens](../../README.md#refreshing-tokens) explains continuity with the original authentication.

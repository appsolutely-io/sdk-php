# Design decisions

These records explain choices in the current implementation. For installation and usage, see the [package guide](../../README.md).

## Runtime services

- [Framework-free service interfaces](framework-free-services.md)

## Contracts and tooling

- [Exercise handwritten clients through their real readers](contract-tests-through-real-readers.md)
- [Keep API documents and compatibility metadata consistent](pin-recovery.md)

## Authentication

- [Restrict ID token trust to this client and asymmetric signing keys](oidc-trust-boundary.md)
- [Refuse serialization of credential-bearing clients](secret-containment.md)

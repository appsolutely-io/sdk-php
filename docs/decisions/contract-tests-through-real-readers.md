# Exercise handwritten clients through their real readers

## Decision

Keep typed PHP resources and models handwritten, and verify them against pinned API documents by exercising the actual request and response paths.

## Rationale

A matching operation name is insufficient evidence that a method sends the right request. A method can call another operation, drop filters on a later page or omit an optional body member. Likewise, a schema label alone cannot prove that the matching model reads the answer. The contract tests drive requests, sample each documented answer, and track which fields and accessors the real model uses. Required, nullable and nested fields must agree with the document.

API answers and webhook records use the same model readers because they share record shapes. The reader chooses an API response exception or a webhook payload exception at the receiving boundary; the latter lets endpoint code decide whether to request redelivery.

## Alternatives and costs

Checking source strings or schema labels alone misses runtime request and reader mismatches. Separate API and webhook model sets duplicate shape and parsing rules. Fixture-only assertions can agree with an incorrect fixture while missing a documented field. Exercising both directions costs test-driver and schema-sampler code, including support for composed schemas. A known discrepancy between a document and the site's payload needs an explicit, guarded sampling override, rather than weakening all models.

## Current implementation

[`ResourceContractTest`](../../tests/Contract/ResourceContractTest.php) checks request lines, each page's query, request bodies, audiences and successful answer samples. [`Fields`](../../src/Model/Fields.php) records field paths and accessors; [`SchemaReads`](../../tests/Contract/SchemaReads.php) compares those reads with schemas. [`SchemaModel`](../../src/Model/SchemaModel.php) couples a model's schema and reader so callers cannot select them independently. [`WebhookContractTest`](../../tests/Contract/WebhookContractTest.php) and [`AdminModelContractTest`](../../tests/Contract/AdminModelContractTest.php) reuse the schema checks. [`SiteApiOverrides`](../../tests/Contract/SiteApiOverrides.php) names Site API sampling exceptions, and discrepancy assertions fail when their assumptions cease to hold.

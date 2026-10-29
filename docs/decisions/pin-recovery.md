# Keep API documents and compatibility metadata consistent

## Decision

Pin both API documents and their compatibility metadata in one recoverable operation. Preserve originals and permissions, and refuse to overwrite leftover recovery files.

## Rationale

A package that contains one document but identifies another revision cannot explain what its contract tests prove. Preparing temporary files before replacement prevents a failed write from partially updating the pin. Backups are also necessary: sequential renames can fail after one target has already changed. A leftover backup may be the only original, so another run must not overwrite it.

## Alternatives and costs

Writing targets directly permits a partial pin. Temporary files without backups still leave mixed contents if a later rename fails. Deleting recovery files automatically can destroy the only original. The chosen design costs extra files and explicit recovery when cleanup or restoration fails; it is recovery for reported filesystem errors, not a transaction that makes multiple renames indivisible to concurrent readers. A successful pin with an undeletable redundant backup remains successful and reports a cleanup warning.

## Current implementation

[`tools/pin-contract.php`](../../tools/pin-contract.php) uses `writeAll()`, `restoreAll()`, `keepMode()` and `refuseLeftovers()`. It writes [`Contract`](../../src/Contract.php) and both files under `tests/Contract/` from the same source ref. [`PinContractTest`](../../tests/Contract/PinContractTest.php) covers read failures, write and rename failures, leftovers, permission preservation and successful cleanup. Run pinning with no concurrent reader or writer of these targets.

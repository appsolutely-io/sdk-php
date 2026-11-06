<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The call conflicts with the current state of the site (a 409), such as an
 * inactive subscription or a request with the same `Idempotency-Key` still
 * running; `$retryAfter` says how long to wait when the latter.
 */
final class ConflictException extends ApiException {}

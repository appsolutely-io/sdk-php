<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The rate-limit budget is spent (a 429): wait `$retryAfter` seconds before
 * sending again; `$rateLimit` names the spent quota.
 */
final class RateLimitedException extends ApiException {}

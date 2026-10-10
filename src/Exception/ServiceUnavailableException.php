<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The site cannot answer now (a 503): `$retryAfter`, when sent, says how
 * many seconds to wait.
 */
final class ServiceUnavailableException extends ApiException {}

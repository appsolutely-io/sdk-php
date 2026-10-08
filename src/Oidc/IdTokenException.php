<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use RuntimeException;

/**
 * An ID token failed verification; the sign-in must not be trusted.
 */
final class IdTokenException extends RuntimeException implements AppsolutelyException {}

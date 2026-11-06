<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The credential may not make this call (a 403); `$challenge` holds the
 * `WWW-Authenticate` header naming a missing scope, and the problem may name
 * the missing ability.
 */
final class ForbiddenException extends ApiException {}

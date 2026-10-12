<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The site could not read the request (a 400), such as an
 * `idempotency-key-invalid` key; sending it again unchanged fails the same way.
 */
final class BadRequestException extends ApiException {}

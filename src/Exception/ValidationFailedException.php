<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The site refused the request's content: `$errors` holds the messages for
 * each refused field (a 422 `validation-failed`).
 */
final class ValidationFailedException extends ApiException {}

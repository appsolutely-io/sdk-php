<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use Throwable;

/**
 * Implemented by every exception this package throws, so a caller can catch
 * all of them in one clause without catching unrelated runtime errors.
 */
interface AppsolutelyException extends Throwable {}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * A value passed to Config is refused, or a PSR service it leaves to
 * discovery cannot be found.
 */
final class InvalidConfigException extends InvalidArgumentValueException {}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use InvalidArgumentException;

/**
 * An argument a constructor or method of this package refuses. A subclass
 * names what was refused where that is worth catching on its own: a Config
 * value, a webhook signing secret, a cache key. Catching this class catches
 * every one of them.
 *
 * The name does not repeat PHP's InvalidArgumentException, which this
 * extends, so an import cannot be mistaken for PHP's own class.
 */
class InvalidArgumentValueException extends InvalidArgumentException implements AppsolutelyException {}

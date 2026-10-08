<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

/**
 * A key the PSR-16 cache this package ships refuses.
 */
final class InvalidCacheKeyException extends InvalidArgumentValueException implements CacheInvalidArgumentException {}

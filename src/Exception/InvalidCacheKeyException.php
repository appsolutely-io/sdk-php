<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

final class InvalidCacheKeyException extends \InvalidArgumentException implements CacheInvalidArgumentException, AppsolutelyException {}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Cache;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use Psr\SimpleCache\InvalidArgumentException;

final class InvalidCacheKeyException extends \InvalidArgumentException implements InvalidArgumentException, AppsolutelyException {}

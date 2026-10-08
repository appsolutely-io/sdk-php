<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use InvalidArgumentException;

final class InvalidConfigException extends InvalidArgumentException implements AppsolutelyException {}

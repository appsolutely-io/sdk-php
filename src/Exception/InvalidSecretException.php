<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use InvalidArgumentException;

final class InvalidSecretException extends InvalidArgumentException implements AppsolutelyException {}

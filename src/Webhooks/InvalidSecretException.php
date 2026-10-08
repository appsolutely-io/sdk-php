<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use InvalidArgumentException;

final class InvalidSecretException extends InvalidArgumentException implements AppsolutelyException {}

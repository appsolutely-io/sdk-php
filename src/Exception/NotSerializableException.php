<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use LogicException;

/**
 * An object holding the client secret was serialized or unserialized: keep
 * the Config, or the values to build it, where the secret already lives.
 */
final class NotSerializableException extends LogicException implements AppsolutelyException {}

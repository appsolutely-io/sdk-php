<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use LogicException;

/**
 * An object holding the client secret or a webhook signing secret was
 * serialized or unserialized: keep the values to build it again where the
 * secret already lives.
 */
final class NotSerializableException extends LogicException implements AppsolutelyException {}

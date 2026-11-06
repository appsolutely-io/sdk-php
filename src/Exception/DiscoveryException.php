<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use RuntimeException;

/**
 * The discovery document or the key set it points to cannot be used.
 */
final class DiscoveryException extends RuntimeException implements AppsolutelyException {}

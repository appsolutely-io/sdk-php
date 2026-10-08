<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use RuntimeException;

/**
 * The request never produced a response: DNS, connection, TLS or timeout.
 */
final class TransportException extends RuntimeException implements AppsolutelyException {}

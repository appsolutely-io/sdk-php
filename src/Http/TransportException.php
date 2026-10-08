<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use RuntimeException;

/**
 * The request never produced a response: DNS, connection, TLS or timeout.
 */
final class TransportException extends RuntimeException implements AppsolutelyException {}

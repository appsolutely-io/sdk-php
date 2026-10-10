<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use RuntimeException;

/**
 * A verified delivery of a known event type whose `data` does not have the
 * shape that type is sent with. The delivery itself is genuine: answer it
 * with a 5xx so the site retries it, and read `$envelope->data` for what
 * arrived.
 */
final class UnexpectedPayloadException extends RuntimeException implements AppsolutelyException {}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use RuntimeException;

/**
 * The delivery cannot be shown to come from Appsolutely: answer it with a 4xx
 * and do not act on it.
 */
final class WebhookVerificationException extends RuntimeException implements AppsolutelyException {}

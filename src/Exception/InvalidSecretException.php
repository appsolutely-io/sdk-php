<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * A webhook signing secret passed to Verifier or WebhookFactory is not a
 * Standard Webhooks secret.
 */
final class InvalidSecretException extends InvalidArgumentValueException {}

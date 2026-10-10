<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use RuntimeException;

/**
 * A verified delivery of a known event type whose `data` does not have the
 * shape that type is sent with: a required field missing, or a field of
 * another type. The message names the event type and the field, never the
 * value. The delivery itself is genuine: answer it with a 5xx so the site
 * retries it, and read `$envelope->data` for what arrived.
 *
 * The same fault in an answer to a Site API call is an
 * UnexpectedResponseException. The two stay apart because they are handled
 * apart: this one at a webhook endpoint, whose answer decides whether the
 * site sends the delivery again, that one where the call was made.
 */
final class UnexpectedPayloadException extends RuntimeException implements AppsolutelyException {}

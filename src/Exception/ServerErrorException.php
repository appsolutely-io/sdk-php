<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The site, or a gateway in front of it, failed to answer (a 5xx). A read
 * may be tried again; under an Idempotency-Key a 500 leaves the outcome
 * unknown until the current state is read. ServiceUnavailableException
 * covers the 503 the site sends when it asks to be called back later.
 */
class ServerErrorException extends ApiException {}

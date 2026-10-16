<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin;

use Appsolutely\Sdk\Api\ApiResponse;

/**
 * A record read from the Administrator API with the version it was read at:
 * an update or a delete is made only over that version, named in If-Match,
 * and a record changed since is refused rather than overwritten.
 *
 * @template-covariant T
 */
final readonly class Versioned
{
    /**
     * @param T $record
     * @param string $etag the version answered, to pass to an update or a delete; a read whose answer names none is refused as an UnexpectedResponseException
     */
    public function __construct(
        public mixed $record,
        public string $etag,
        public ApiResponse $response,
    ) {}
}

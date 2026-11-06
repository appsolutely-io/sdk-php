<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

/**
 * The answer to a write sent with an Idempotency-Key: the resource, and
 * whether the site answered with the stored answer to an earlier request
 * under the same key rather than running the write again.
 *
 * @template-covariant T
 */
final readonly class KeyedResult
{
    /**
     * @param T $value
     * @param string $idempotencyKey the key the write was sent with, the caller's or a fresh UUID; send it again to retry the same write
     * @param bool $replayed whether this is the stored answer to an earlier request with the same key
     */
    public function __construct(
        public mixed $value,
        public string $idempotencyKey,
        public bool $replayed,
        public ApiResponse $response,
    ) {}
}

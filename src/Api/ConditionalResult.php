<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

/** @template-covariant T */
final readonly class ConditionalResult
{
    /**
     * @param T|null $value null only for a bodyless 304 answer
     * @internal
     */
    public function __construct(
        public mixed $value,
        public ApiResponse $response,
    ) {}

    public function notModified(): bool
    {
        return $this->response->status === 304;
    }
}

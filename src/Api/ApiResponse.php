<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Psr\Http\Message\ResponseInterface;

/**
 * A successful Site API answer: the resource itself (or a page of them) as
 * decoded JSON, with what the answer says about itself.
 */
final readonly class ApiResponse
{
    /**
     * @param array<mixed>|null $data the decoded JSON body; null when the answer has none, such as a 204
     * @param string|null $requestId the X-Request-Id the site answered with, or the one sent when it named none
     * @param bool $replayed whether this is the stored answer to an earlier request with the same Idempotency-Key
     * @param string|null $idempotencyKey the Idempotency-Key the request carried, if any
     * @param string|null $location where a created resource is read back, from a 201's Location
     */
    public function __construct(
        public int $status,
        public ?array $data,
        public ?string $requestId,
        public RateLimit $rateLimit,
        public bool $replayed,
        public ?string $idempotencyKey,
        public ?string $location,
        public ResponseInterface $response,
    ) {}
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Testing;

use Appsolutely\Sdk\Admin\AdminOperation;

/**
 * One call a FakeAdminClient received, as it went over the wire.
 */
final readonly class RecordedAdminCall
{
    /**
     * @param array<string, string> $pathParameters the values of the path's placeholders, decoded
     * @param array<array-key, mixed> $query the query, decoded
     * @param array<mixed>|null $body the JSON body, decoded; null when none was sent
     * @param string|null $idempotencyKey the Idempotency-Key sent, if any
     * @param string|null $ifMatch the If-Match sent, if any: the version a write was made over
     * @param string|null $token the bearer token sent
     */
    public function __construct(
        public AdminOperation $operation,
        public array $pathParameters,
        #[\SensitiveParameter]
        public array $query,
        public ?array $body,
        public ?string $idempotencyKey,
        public ?string $ifMatch,
        public string $requestId,
        #[\SensitiveParameter]
        public ?string $token,
    ) {}
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Testing;

use Appsolutely\Sdk\Api\Operation;

/**
 * One call a FakeClient received, as it went over the wire.
 */
final readonly class RecordedCall
{
    /**
     * @param array<string, string> $pathParameters the values of the path's placeholders, decoded
     * @param array<array-key, mixed> $query the query, decoded (`subjects[]` as a list)
     * @param array<mixed>|null $body the JSON body, decoded; null when none was sent
     * @param string|null $idempotencyKey the Idempotency-Key sent, if any
     * @param string|null $token the bearer token sent: the administrator's, a member's, or none
     */
    public function __construct(
        public Operation $operation,
        public array $pathParameters,
        #[\SensitiveParameter]
        public array $query,
        public ?array $body,
        public ?string $idempotencyKey,
        public string $requestId,
        #[\SensitiveParameter]
        public ?string $token,
    ) {}
}

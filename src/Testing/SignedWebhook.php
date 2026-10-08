<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Testing;

/**
 * A delivery as it reaches the integrator's endpoint: the raw body and the
 * three Standard Webhooks headers. A test helper: not for production code.
 */
final readonly class SignedWebhook
{
    /**
     * @param array{webhook-id: string, webhook-timestamp: string, webhook-signature: string} $headers
     */
    public function __construct(
        public string $id,
        public string $body,
        #[\SensitiveParameter]
        public array $headers,
    ) {}
}

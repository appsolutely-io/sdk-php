<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What sending an event again did, across every endpoint of the backend's
 * mode that was owed it: the deliveries queued again, and the endpoints that
 * refuse it now, each with why.
 */
final readonly class Redelivery
{
    /**
     * @param list<EndpointWebhookDelivery> $redelivered the deliveries queued again, as they stand now
     * @param array<string, string> $refused the reason each endpoint refused it, by endpoint id
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public array $redelivered,
        public array $refused,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $refused = [];
        foreach ($json->objects('refused') as $refusal) {
            $refused[$refusal->string('endpoint_id')] = $refusal->string('reason');
        }

        return new self(array_map(EndpointWebhookDelivery::from(...), $json->objects('data')), $refused, $json->extra());
    }
}

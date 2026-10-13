<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A delivery of an event to one of the backend's endpoints.
 */
final readonly class EndpointWebhookDelivery implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'EndpointWebhookDelivery';

    /**
     * @param string $endpointId the endpoint it was owed to
     */
    public function __construct(
        public string $endpointId,
        public WebhookDelivery $delivery,
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self($json->string('endpoint_id'), WebhookDelivery::from($json->except('endpoint_id')));
    }
}

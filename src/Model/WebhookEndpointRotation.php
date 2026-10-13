<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A rotation of an endpoint's signing secret, just started: deliveries are
 * signed with the old secret and this one together until the rotation is
 * completed or cancelled with its id.
 */
final readonly class WebhookEndpointRotation implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'WebhookEndpointRotation';

    /**
     * @param string $rotationId what completing or cancelling the rotation names it by
     * @param string $secret the new `whsec_` secret: sent this once, so add it to the verifier now
     */
    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $rotationId,
        #[\SensitiveParameter]
        public string $secret,
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(WebhookEndpoint::from($json->except('rotation_id', 'secret')), $json->string('rotation_id'), $json->string('secret'));
    }

    /**
     * What var_dump() and print_r() show: the secret is replaced.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['endpoint' => $this->endpoint, 'rotationId' => $this->rotationId, 'secret' => '[redacted]'];
    }
}

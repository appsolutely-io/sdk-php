<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * An endpoint just registered, with the secret its deliveries are signed
 * with: sent this once, so keep it now.
 */
final readonly class WebhookEndpointCreated implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'WebhookEndpointWithSecret';

    /**
     * @param string $secret the `whsec_` secret to verify its deliveries with
     */
    public function __construct(
        public WebhookEndpoint $endpoint,
        #[\SensitiveParameter]
        public string $secret,
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(WebhookEndpoint::from($json->except('secret')), $json->string('secret'));
    }

    /**
     * What var_dump() and print_r() show: the secret is replaced.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['endpoint' => $this->endpoint, 'secret' => '[redacted]'];
    }
}

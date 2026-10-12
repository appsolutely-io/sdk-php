<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\PushDevice;

/**
 * The devices the member is reached on by push.
 */
final readonly class PushDevices
{
    /** @internal obtain it from Client::forMember()->api()->me()->pushDevices() */
    public function __construct(private Caller $caller) {}

    /**
     * Registers a device, or refreshes it when the token is already known.
     *
     * @param string $token the push provider's device token
     * @param 'ios'|'android'|'web' $platform
     * @param 'apns'|'fcm'|null $provider the platform's own when left out
     * @param array<string, mixed>|null $metadata
     */
    #[Endpoint(Operation::CreateMePushDevice)]
    public function register(
        #[\SensitiveParameter]
        string $token,
        string $platform,
        ?string $provider = null,
        ?string $appIdentifier = null,
        ?string $installationId = null,
        ?array $metadata = null,
    ): PushDevice {
        $device = array_filter([
            'token' => $token,
            'platform' => $platform,
            'provider' => $provider,
            'app_identifier' => $appIdentifier,
            'installation_id' => $installationId,
            'metadata' => $metadata,
        ], static fn(mixed $value): bool => $value !== null);

        return $this->caller->read(Operation::CreateMePushDevice, PushDevice::SCHEMA, PushDevice::from(...), body: $device);
    }

    /**
     * Stops reaching the member on the device with this token.
     */
    #[Endpoint(Operation::UnregisterMePushDevice)]
    public function unregister(#[\SensitiveParameter] string $token): void
    {
        $this->caller->send(Operation::UnregisterMePushDevice, body: ['token' => $token]);
    }
}

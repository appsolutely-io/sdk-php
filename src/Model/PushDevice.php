<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A device the member is reached on by push.
 */
final readonly class PushDevice
{
    /** @internal */
    public const string SCHEMA = 'PushDevice';

    /**
     * @param string $token the push provider's device token
     * @param string $platform `ios`, `android` or `web`
     * @param string $provider `apns` or `fcm`
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        public string $platform,
        public string $provider,
        public ?string $appIdentifier,
        public ?string $installationId,
        public ?DateTimeImmutable $lastUsedAt,
        #[\SensitiveParameter]
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            token: $json->string('token'),
            platform: $json->string('platform'),
            provider: $json->string('provider'),
            appIdentifier: $json->optionalString('app_identifier'),
            installationId: $json->optionalString('installation_id'),
            lastUsedAt: $json->optionalTime('last_used_at'),
            attributes: $json->all(),
        );
    }
}

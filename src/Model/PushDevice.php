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
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
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
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            token: $json->string('token'),
            platform: $json->string('platform'),
            provider: $json->string('provider'),
            appIdentifier: $json->nullableString('app_identifier'),
            installationId: $json->nullableString('installation_id'),
            lastUsedAt: $json->nullableTime('last_used_at'),
            extra: $json->extra(),
        );
    }
}

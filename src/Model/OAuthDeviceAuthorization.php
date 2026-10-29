<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OAuthDeviceAuthorization implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OAuthDeviceAuthorization';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $deviceCode,
        #[\SensitiveParameter]
        public string $userCode,
        public string $verificationUri,
        public ?string $verificationUriComplete,
        public int $expiresIn,
        public ?int $interval,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            deviceCode: $json->string('device_code'),
            userCode: $json->string('user_code'),
            verificationUri: $json->string('verification_uri'),
            verificationUriComplete: $json->nullableString('verification_uri_complete'),
            expiresIn: $json->int('expires_in'),
            interval: $json->nullableInt('interval'),
            extra: $json->extra(),
        );
    }
}

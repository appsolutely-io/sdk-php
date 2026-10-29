<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use Appsolutely\Sdk\Exception\NotSerializableException;

/**
 * var_export(), json_encode() and array casts still expose the token fields.
 * Never log or persist these objects.
 */
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

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['deviceCode' => '[redacted]', 'userCode' => '[redacted]', 'verificationUri' => $this->verificationUri, 'verificationUriComplete' => '[redacted]', 'expiresIn' => $this->expiresIn, 'interval' => $this->interval];
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new NotSerializableException('An OAuth credential holder is not serialized.');
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('An OAuth credential holder is not unserialized.');
    }
}

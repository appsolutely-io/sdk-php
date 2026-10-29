<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Support\OptionalFields;

/**
 * var_export(), json_encode() and array casts still expose the token fields.
 * Never log or persist these objects.
 */
final readonly class DeviceCodeGrant implements TokenGrant
{
    public function __construct(
        #[\SensitiveParameter]
        public string $deviceCode,
        public ?string $scope = null,
    ) {
        if ($deviceCode === '') {
            throw new \Appsolutely\Sdk\Exception\InvalidArgumentValueException('The selected OAuth grant requires non-empty values.');
        }
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return OptionalFields::withoutNulls(['grant_type' => 'urn:ietf:params:oauth:grant-type:device_code', 'device_code' => $this->deviceCode, 'scope' => $this->scope]);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['deviceCode' => '[redacted]', 'scope' => $this->scope];
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

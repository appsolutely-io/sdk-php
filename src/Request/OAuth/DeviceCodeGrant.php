<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

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
        return array_filter(['grant_type' => 'urn:ietf:params:oauth:grant-type:device_code', 'device_code' => $this->deviceCode, 'scope' => $this->scope], static fn(?string $value): bool => $value !== null);
    }
}

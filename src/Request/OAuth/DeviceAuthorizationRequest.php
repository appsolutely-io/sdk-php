<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

final readonly class DeviceAuthorizationRequest
{
    public function __construct(public ?string $scope = null) {}

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->scope === null ? [] : ['scope' => $this->scope];
    }
}

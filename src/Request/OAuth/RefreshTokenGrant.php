<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

use Appsolutely\Sdk\Exception\NotSerializableException;

final readonly class RefreshTokenGrant implements TokenGrant
{
    public function __construct(
        #[\SensitiveParameter]
        public string $refreshToken,
        #[\SensitiveParameter]
        public ?string $scope = null,
    ) {
        if ($refreshToken === '') {
            throw new \Appsolutely\Sdk\Exception\InvalidArgumentValueException('The selected OAuth grant requires non-empty values.');
        }
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return array_filter(['grant_type' => 'refresh_token', 'refresh_token' => $this->refreshToken, 'scope' => $this->scope], static fn(?string $value): bool => $value !== null);
    }
    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['refreshToken' => '[redacted]', 'scope' => $this->scope];
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

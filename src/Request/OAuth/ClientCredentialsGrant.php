<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

use Appsolutely\Sdk\Exception\NotSerializableException;

final readonly class ClientCredentialsGrant implements TokenGrant
{
    public function __construct(
        #[\SensitiveParameter]
        public ?string $scope = null,
    ) {}

    /** @return array<string, string> */
    public function fields(): array
    {
        return array_filter(['grant_type' => 'client_credentials', 'scope' => $this->scope], static fn(?string $value): bool => $value !== null);
    }
    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['scope' => $this->scope];
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

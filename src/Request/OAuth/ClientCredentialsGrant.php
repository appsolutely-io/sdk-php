<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

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
}

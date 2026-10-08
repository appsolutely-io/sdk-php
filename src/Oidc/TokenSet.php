<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use DateTimeImmutable;

final readonly class TokenSet
{
    public function __construct(
        #[\SensitiveParameter]
        public string $accessToken,
        public string $tokenType,
        public ?DateTimeImmutable $expiresAt = null,
        #[\SensitiveParameter]
        public ?string $refreshToken = null,
        public ?string $scope = null,
        #[\SensitiveParameter]
        public ?IdToken $idToken = null,
    ) {}
}

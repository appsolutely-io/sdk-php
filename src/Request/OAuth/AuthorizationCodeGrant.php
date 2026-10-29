<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

final readonly class AuthorizationCodeGrant implements TokenGrant
{
    public function __construct(
        #[\SensitiveParameter]
        public string $code,
        #[\SensitiveParameter]
        public string $redirectUri,
        #[\SensitiveParameter]
        public string $codeVerifier,
        public ?string $scope = null,
    ) {
        if ($code === '' || $redirectUri === '' || $codeVerifier === '') {
            throw new \Appsolutely\Sdk\Exception\InvalidArgumentValueException('The selected OAuth grant requires non-empty values.');
        }
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return array_filter(['grant_type' => 'authorization_code', 'code' => $this->code, 'redirect_uri' => $this->redirectUri, 'code_verifier' => $this->codeVerifier, 'scope' => $this->scope], static fn(?string $value): bool => $value !== null);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Support\OptionalFields;

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
        return OptionalFields::withoutNulls(['grant_type' => 'authorization_code', 'code' => $this->code, 'redirect_uri' => $this->redirectUri, 'code_verifier' => $this->codeVerifier, 'scope' => $this->scope]);
    }
    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['code' => '[redacted]', 'redirectUri' => $this->redirectUri, 'codeVerifier' => '[redacted]', 'scope' => $this->scope];
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

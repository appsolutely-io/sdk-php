<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

use Appsolutely\Sdk\Exception\NotSerializableException;

final readonly class RevocationRequest
{
    public function __construct(#[\SensitiveParameter] public string $token, public ?string $tokenTypeHint = null) {}

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->tokenTypeHint === null ? ['token' => $this->token] : ['token' => $this->token, 'token_type_hint' => $this->tokenTypeHint];
    }
    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['token' => '[redacted]', 'tokenTypeHint' => $this->tokenTypeHint];
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

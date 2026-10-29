<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

final readonly class RevocationRequest
{
    public function __construct(#[\SensitiveParameter] public string $token, public ?string $tokenTypeHint = null) {}

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->tokenTypeHint === null ? ['token' => $this->token] : ['token' => $this->token, 'token_type_hint' => $this->tokenTypeHint];
    }
}

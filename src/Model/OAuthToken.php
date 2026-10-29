<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OAuthToken implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OAuthToken';

    public const string TOKEN_TYPE_BEARER = 'Bearer';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        #[\SensitiveParameter]
        public ?string $refreshToken,
        #[\SensitiveParameter]
        public ?string $idToken,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            accessToken: $json->string('access_token'),
            tokenType: $json->string('token_type'),
            expiresIn: $json->int('expires_in'),
            refreshToken: $json->nullableString('refresh_token'),
            idToken: $json->nullableString('id_token'),
            extra: $json->extra(),
        );
    }
}

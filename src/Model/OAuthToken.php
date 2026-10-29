<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use Appsolutely\Sdk\Exception\NotSerializableException;

/**
 * var_export(), json_encode() and array casts still expose the token fields.
 * Never log or persist these objects.
 */
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

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['accessToken' => '[redacted]', 'tokenType' => $this->tokenType, 'expiresIn' => $this->expiresIn, 'refreshToken' => '[redacted]', 'idToken' => '[redacted]'];
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

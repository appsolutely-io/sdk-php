<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeHubToken implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeHubToken';

    /**
     * @param list<string> $topics
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $url,
        public array $topics,
        public int $expiresIn,
        public string $tokenType,
        #[\SensitiveParameter]
        public ?string $accessToken,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            url: $json->string('url'),
            topics: $json->strings('topics'),
            expiresIn: $json->int('expires_in'),
            tokenType: $json->string('token_type'),
            accessToken: $json->nullableString('access_token'),
            extra: $json->extra(),
        );
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new \Appsolutely\Sdk\Exception\NotSerializableException('A realtime credential holder is not serialized.');
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new \Appsolutely\Sdk\Exception\NotSerializableException('A realtime credential holder is not unserialized.');
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'accessToken' => '[redacted]',
        ];
    }
}

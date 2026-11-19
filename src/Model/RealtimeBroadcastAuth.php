<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeBroadcastAuth implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeBroadcastAuth';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $auth,
        public ?string $channelData,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            auth: $json->string('auth'),
            channelData: $json->nullableString('channel_data'),
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
            'auth' => '[redacted]',
        ];
    }
}

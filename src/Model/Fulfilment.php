<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A delivery token as it stands once the backend fulfilled it.
 */
final readonly class Fulfilment implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'Fulfilment';

    /**
     * @param string $token the delivery token fulfilled
     * @param string $status where the delivery stands, such as `delivered`
     * @param DateTimeImmutable|null $deliveredAt when it was delivered
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        public string $status,
        public ?DateTimeImmutable $deliveredAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            token: $json->string('token'),
            status: $json->string('status'),
            deliveredAt: $json->nullableTime('delivered_at'),
            extra: $json->extra(),
        );
    }
}

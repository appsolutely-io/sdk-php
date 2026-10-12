<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A subscription bought by quantity, and its seats.
 */
final readonly class SeatPool
{
    /** @internal */
    public const string SCHEMA = 'SeatPool';

    /**
     * @param string $id the subscription's id
     * @param string|null $product the product's slug
     * @param int $quantity the seats paid for now
     * @param int $scheduled the seats paid for from the next renewal
     * @param int $ceiling the most seats it can hold now
     * @param int $occupied the seats held
     * @param list<Seat> $seats
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $product,
        public int $quantity,
        public int $scheduled,
        public int $ceiling,
        public int $occupied,
        public array $seats,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            product: $json->nullableString('product'),
            quantity: $json->int('quantity'),
            scheduled: $json->int('scheduled'),
            ceiling: $json->int('ceiling'),
            occupied: $json->int('occupied'),
            seats: array_map(Seat::from(...), $json->objects('seats')),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What dissolving an organisation does to what it has paid for.
 */
final readonly class OrganisationDissolutionConsequence implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationDissolutionConsequence';

    public const string REFUND_NONE = 'none';
    public const string SEATS_END_IMMEDIATELY = 'immediately';

    /**
     * @param string $refund one of the REFUND_ constants
     * @param string $seatsEnd one of the SEATS_END_ constants
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $refund,
        public string $seatsEnd,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            refund: $json->string('refund'),
            seatsEnd: $json->string('seats_end'),
            extra: $json->extra(),
        );
    }
}

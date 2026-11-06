<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One seat pool of an organisation, as dissolving it would stop it.
 */
final readonly class OrganisationDissolutionPool implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationDissolutionPool';

    public const string STOP_IMMEDIATE_LOCAL = 'immediate_local';
    public const string STOP_PROVIDER_IMMEDIATE = 'provider_immediate';
    public const string STOP_UNSUPPORTED = 'unsupported';

    /**
     * @param DateTimeImmutable|null $paidUntil the end of the period paid for, null when none is
     * @param string $stop how the pool's billing would be stopped, one of the STOP_ constants
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $plan,
        public int $quantity,
        public int $occupied,
        public ?DateTimeImmutable $paidUntil,
        public string $stop,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            plan: $json->string('plan'),
            quantity: $json->int('quantity'),
            occupied: $json->int('occupied'),
            paidUntil: $json->nullableTime('paid_until'),
            stop: $json->string('stop'),
            extra: $json->extra(),
        );
    }
}

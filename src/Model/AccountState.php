<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The current state of one member's account, as the `account.*` webhook
 * deliveries describe it: for a reconciliation, or a catch-up after missed
 * deliveries.
 */
final readonly class AccountState
{
    /** @internal */
    public const string SCHEMA = 'AccountState';

    /**
     * @param string $subject the member's id
     * @param int $sequence increases with every change, so an older state is recognised as one
     * @param list<Entitlement> $entitlements
     * @param array<string, mixed> $totals
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $subject,
        public int $sequence,
        public string $status,
        public ?string $email,
        public bool $emailVerified,
        public array $entitlements,
        public array $totals,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            subject: $json->string('subject'),
            sequence: $json->int('sequence'),
            status: $json->string('status'),
            email: $json->optionalString('email'),
            emailVerified: $json->bool('email_verified'),
            entitlements: array_map(Entitlement::from(...), $json->objects('entitlements')),
            totals: $json->map('totals'),
            attributes: $json->all(),
        );
    }
}

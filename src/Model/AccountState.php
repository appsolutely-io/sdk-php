<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * An account's whole state, as an API answer and every `account.*`
 * delivery carry it: for a reconciliation, or a catch-up after missed
 * deliveries.
 *
 * Keep the highest `sequence` per `subject` and ignore a state whose sequence
 * is not higher; overwrite your copy with one that is. Decide what to do from
 * `status`, not from the event type: changes close together can arrive as
 * fewer deliveries, each carrying the state when it was sent.
 */
final readonly class AccountState
{
    /** @internal */
    public const string SCHEMA = 'AccountState';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_SUSPENDED = 'suspended';

    /** Terminal: no address, no grants, and nothing more is sent about the subject. */
    public const string STATUS_ERASED = 'erased';

    /**
     * @param string $subject the account's public name, the `sub` its ID token carries
     * @param int $sequence increases with every change, so an older state is recognised as one
     * @param string $status one of the STATUS_ constants
     * @param string|null $email the address the account holds now; null once erased
     * @param list<Entitlement> $entitlements every live grant, then what another authority says the account holds
     * @param array<string, int> $totals one number per entitlement key, as the site checks it; empty when there are none
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $subject,
        public int $sequence,
        public string $status,
        public ?string $email,
        public bool $emailVerified,
        public array $entitlements,
        public array $totals,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            subject: $json->string('subject'),
            sequence: $json->int('sequence'),
            status: $json->string('status'),
            email: $json->nullableString('email'),
            emailVerified: $json->bool('email_verified'),
            entitlements: array_map(Entitlement::from(...), $json->objects('entitlements')),
            totals: $json->intMap('totals'),
            extra: $json->extra(),
        );
    }
}

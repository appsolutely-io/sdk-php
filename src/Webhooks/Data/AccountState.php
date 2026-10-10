<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

/**
 * An account's whole state, as every `account.*` delivery carries it.
 *
 * Keep the highest `sequence` per `subject` and ignore a state whose sequence
 * is not higher; overwrite your copy with one that is. Decide what to do from
 * `status`, not from the event type: changes close together can arrive as
 * fewer deliveries, each carrying the state when it was sent.
 */
final readonly class AccountState
{
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_SUSPENDED = 'suspended';

    /** Terminal: no address, no grants, and nothing more is sent about the subject. */
    public const string STATUS_ERASED = 'erased';

    /**
     * @param string $subject the account's public name, the `sub` its ID token carries
     * @param string $status one of the STATUS_ constants
     * @param string|null $email the address the account holds now; null once erased
     * @param list<Entitlement> $entitlements every live grant, then what another authority says the account holds
     * @param array<string, int> $totals one number per entitlement key, as the site checks it; empty when there are none
     * @param array<string, mixed> $extra fields the site sent that this class does not name
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

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('subject'),
            $fields->int('sequence'),
            $fields->string('status'),
            $fields->nullableString('email'),
            $fields->bool('email_verified'),
            array_map(Entitlement::read(...), $fields->objects('entitlements')),
            $fields->intMap('totals'),
            $fields->extra(),
        );
    }
}

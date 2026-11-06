<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An account's state as the backend's list of every account gives it: the
 * same state a delivery or a single read carries, and when what its
 * entitlements read last changed.
 */
final readonly class ListedAccountState implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'ListedAccountState';

    /**
     * @param DateTimeImmutable $entitlementsChangedAt when the account's entitlements last changed, to the second: for display and ordering; start a walk from a page's `nextChangedSince`, never from this
     */
    public function __construct(
        public AccountState $state,
        public DateTimeImmutable $entitlementsChangedAt,
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(AccountState::from($json->except('entitlements_changed_at')), $json->time('entitlements_changed_at'));
    }
}

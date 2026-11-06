<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What dissolving an organisation would do now, and what stops it.
 */
final readonly class OrganisationDissolutionPreview implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationDissolutionPreview';

    public const string BLOCKERS_PENDING_ORDER = 'pending_order';
    public const string BLOCKERS_UNFINISHED_SETUP = 'unfinished_setup';
    public const string BLOCKERS_UNRESOLVED_BILLING = 'unresolved_billing';
    public const string BLOCKERS_PENDING_TRANSFER = 'pending_transfer';
    public const string BLOCKERS_FROZEN = 'frozen';

    /**
     * @param string $revision the preview's revision, which changes whenever what it describes does
     * @param list<OrganisationDissolutionPool> $pools
     * @param list<string> $blockers what must be resolved first, each one of the BLOCKERS_ constants; empty when nothing stops it
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $revision,
        public OrganisationDissolutionConsequence $consequence,
        public array $pools,
        public array $blockers,
        public int $invitationsPending,
        public int $membersActive,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            revision: $json->string('revision'),
            consequence: OrganisationDissolutionConsequence::from($json->object('consequence')),
            pools: array_map(OrganisationDissolutionPool::from(...), $json->objects('pools')),
            blockers: $json->strings('blockers'),
            invitationsPending: $json->int('invitations_pending'),
            membersActive: $json->int('members_active'),
            extra: $json->extra(),
        );
    }
}

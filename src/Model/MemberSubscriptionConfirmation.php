<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What became of resuming a member's subscription: applied, or waiting for
 * the member to confirm it on the site's billing pages.
 */
final readonly class MemberSubscriptionConfirmation implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'MemberSubscriptionConfirmation';

    /**
     * @param PortalSession|null $confirmation the visit in which the member confirms it, when the site asks
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public bool $applied,
        public ?PortalSession $confirmation,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $confirmation = $json->nullableObject('confirmation');

        return new self($json->bool('applied'), $confirmation === null ? null : PortalSession::from($confirmation), $json->extra());
    }
}

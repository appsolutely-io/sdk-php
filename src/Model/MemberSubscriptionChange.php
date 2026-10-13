<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What became of a member's change to a subscription: applied, with the
 * subscription as it now stands, or waiting for the member to confirm it on
 * the site's billing pages.
 */
final readonly class MemberSubscriptionChange implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'MemberSubscriptionChange';

    /**
     * @param MemberSubscription|null $subscription the subscription after the change, once applied
     * @param PortalSession|null $confirmation the visit in which the member confirms it, when the site asks
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public bool $applied,
        public ?MemberSubscription $subscription,
        public ?PortalSession $confirmation,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $subscription = $json->nullableObject('subscription');
        $confirmation = $json->nullableObject('confirmation');

        return new self(
            applied: $json->bool('applied'),
            subscription: $subscription === null ? null : MemberSubscription::from($subscription),
            confirmation: $confirmation === null ? null : PortalSession::from($confirmation),
            extra: $json->extra(),
        );
    }
}

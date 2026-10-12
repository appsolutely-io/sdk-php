<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\Referral as ReferralModel;
use Appsolutely\Sdk\Model\ReferralReward;

/**
 * The member's referral code and what it has earned them. A member the
 * site offers no programme to is refused with `referral-unavailable`.
 */
final readonly class Referral
{
    /** @internal obtain it from Client::forMember()->api()->me()->referral() */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetMeReferral)]
    public function get(): ReferralModel
    {
        return $this->caller->read(Operation::GetMeReferral, ReferralModel::SCHEMA, ReferralModel::from(...));
    }

    /**
     * @return Paginator<ReferralReward>
     */
    #[Endpoint(Operation::RewardsMeReferral)]
    public function rewards(int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::RewardsMeReferral, ReferralReward::SCHEMA, ReferralReward::from(...), [], $limit);
    }
}

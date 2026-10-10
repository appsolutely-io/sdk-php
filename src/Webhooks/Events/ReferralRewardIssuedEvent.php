<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\IssuedReferralReward;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `referral.reward_issued`: a reward first released to the member who
 * referred a friend. Nothing about the friend is sent. Book each reward once
 * per `$paymentReference`, or per the envelope's id when that is null.
 */
final readonly class ReferralRewardIssuedEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::REFERRAL_REWARD_ISSUED,
    ];

    /**
     * @param string|null $subject the referrer's account subject, null once the account is gone
     * @param string|null $referralCode the referrer's code the friend redeemed, null once it is gone
     * @param string|null $paymentReference the friend's payment that earned the reward
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        Event $envelope,
        public ?string $subject,
        public ?string $referralCode,
        public ?string $paymentReference,
        public IssuedReferralReward $reward,
        public array $extra = [],
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self(
            $envelope,
            $data->nullableString('subject'),
            $data->nullableString('referral_code'),
            $data->nullableString('payment_reference'),
            IssuedReferralReward::from($data->object('reward')),
            $data->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\AccountState;
use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `account.*`: an account's standing or what it may use changed. Each
 * carries the account's whole state, not the change; see AccountState for
 * how to apply it.
 */
final readonly class AccountEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::ACCOUNT_SUSPENDED,
        EventType::ACCOUNT_REINSTATED,
        EventType::ACCOUNT_ERASED,
        EventType::ACCOUNT_ENTITLEMENTS_CHANGED,
        EventType::ACCOUNT_EMAIL_CHANGED,
    ];

    public function __construct(
        Event $envelope,
        public AccountState $state,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, Fields $data): self
    {
        return new self($envelope, AccountState::read($data));
    }
}

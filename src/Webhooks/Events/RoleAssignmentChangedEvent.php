<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `role.assignment_changed`: one of the app's roles given to or taken from a
 * member, with the member's whole set of roles in the event's mode after the
 * change. Apply the set with the highest sequence held, whatever order the
 * deliveries arrive in.
 */
final readonly class RoleAssignmentChangedEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::ROLE_ASSIGNMENT_CHANGED,
    ];

    public const string CHANGE_ADDED = 'added';

    public const string CHANGE_REMOVED = 'removed';

    /** The app's backend gave or took the role. */
    public const string CAUSE_APP = 'app';

    /** The role was retired, and taken from everyone who held it. */
    public const string CAUSE_ROLE_RETIRED = 'role_retired';

    /**
     * @param string $subject the member's public name, the `sub` its ID token carries
     * @param string $role the role's key
     * @param string $change one of the CHANGE_ constants
     * @param string $cause one of the CAUSE_ constants
     * @param list<string> $roles the member's roles in the event's mode after the change
     * @param int $sequence the member's role sequence, which only rises
     * @param array<array-key, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        Event $envelope,
        public string $subject,
        public string $role,
        public string $change,
        public string $cause,
        public array $roles,
        public int $sequence,
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
            $data->string('subject'),
            $data->string('role'),
            $data->string('change'),
            $data->string('cause'),
            $data->strings('roles'),
            $data->int('sequence'),
            $data->extra(),
        );
    }
}

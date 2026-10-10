<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

/**
 * The event types the site sends, as they appear in an envelope's `type`.
 * Route on them, and subscribe an endpoint by the same strings.
 */
final class EventType
{
    /** An operator suspended the account. */
    public const string ACCOUNT_SUSPENDED = 'account.suspended';

    /** The suspension was lifted. */
    public const string ACCOUNT_REINSTATED = 'account.reinstated';

    /** The account was closed or deleted; nothing more is sent about it. */
    public const string ACCOUNT_ERASED = 'account.erased';

    /** A grant was written or revoked, or started or ended because its date passed. */
    public const string ACCOUNT_ENTITLEMENTS_CHANGED = 'account.entitlements_changed';

    /** The address changed, or whether it is verified did. */
    public const string ACCOUNT_EMAIL_CHANGED = 'account.email_changed';

    /** An operator pressed "Send test delivery" on the subscription. */
    public const string WEBHOOK_PING = 'webhook.ping';

    private function __construct() {}
}

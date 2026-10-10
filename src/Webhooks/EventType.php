<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

/**
 * The event types the site sends, as they appear in an envelope's `type`.
 * Route on them, and subscribe an endpoint by the same strings.
 */
final class EventType
{
    /** An operator pressed "Send test delivery" on the subscription. */
    public const string WEBHOOK_PING = 'webhook.ping';

    private function __construct() {}
}

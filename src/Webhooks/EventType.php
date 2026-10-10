<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

/**
 * The event types the site sends, as they appear in an envelope's `type`.
 * Route on them, and subscribe an endpoint by the same strings.
 */
final class EventType
{
    public const string ARTICLE_CREATED = 'article.created';
    public const string ARTICLE_UPDATED = 'article.updated';
    public const string ARTICLE_DELETED = 'article.deleted';

    public const string PAGE_CREATED = 'page.created';
    public const string PAGE_UPDATED = 'page.updated';
    public const string PAGE_DELETED = 'page.deleted';

    public const string FORM_SUBMITTED = 'form.submitted';

    /** An order's payment settled. */
    public const string ORDER_PAID = 'order.paid';
    public const string ORDER_COMPLETED = 'order.completed';
    public const string ORDER_SHIPPED = 'order.shipped';
    public const string ORDER_CANCELLED = 'order.cancelled';
    public const string ORDER_STATUS_UPDATED = 'order.status_updated';

    /** The shop gave up on being paid for an unpaid order; not a cancellation. */
    public const string ORDER_EXPIRED = 'order.expired';

    /** An expired order came back because its money arrived; an `order.paid` follows. */
    public const string ORDER_REVIVED = 'order.revived';

    /** Money arrived for an order that stays closed. */
    public const string ORDER_PAYMENT_ARRIVED_LATE = 'order.payment_arrived_late';

    /** All of an order's money was returned. */
    public const string ORDER_REFUNDED = 'order.refunded';

    /** All of one payment's money was returned. */
    public const string PAYMENT_REFUNDED = 'payment.refunded';

    /** All of one payment's money is gone, a lost chargeback taking part of it. */
    public const string PAYMENT_REVERSED = 'payment.reversed';

    public const string REFUND_REQUESTED = 'refund.requested';
    public const string REFUND_PROCESSED = 'refund.processed';

    /** A referral reward was first released to the member who referred a friend. */
    public const string REFERRAL_REWARD_ISSUED = 'referral.reward_issued';

    public const string PRODUCT_CREATED = 'product.created';
    public const string PRODUCT_UPDATED = 'product.updated';
    public const string PRODUCT_DELETED = 'product.deleted';

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

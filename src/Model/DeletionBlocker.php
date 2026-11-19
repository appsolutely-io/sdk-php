<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class DeletionBlocker implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'DeletionBlocker';

    public const string CODE_SUSPENDED = 'suspended';
    public const string CODE_CREDENTIALS_CHANGED = 'credentials_changed';
    public const string CODE_ADDRESS_CHANGE_DISPUTABLE = 'address_change_disputable';
    public const string CODE_RECENTLY_CANCELLED = 'recently_cancelled';
    public const string CODE_SUBSCRIPTION_LIVE = 'subscription_live';
    public const string CODE_CHECKOUT_OPEN = 'checkout_open';
    public const string CODE_PAYMENT_SETTLING = 'payment_settling';
    public const string CODE_PAYMENT_PARKED = 'payment_parked';
    public const string CODE_REFUND_PENDING = 'refund_pending';
    public const string CODE_HOST = 'host';
    public const string CODE_ORGANISATION_OWNER = 'organisation_owner';
    public const string CODE_ORGANISATION_BILLING = 'organisation_billing';
    public const string CODE_ORGANISATION_DISSOLVING = 'organisation_dissolving';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $code,
        public string $title,
        public string $resolveUrl,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            code: $json->string('code'),
            title: $json->string('title'),
            resolveUrl: $json->string('resolve_url'),
            extra: $json->extra(),
        );
    }
}

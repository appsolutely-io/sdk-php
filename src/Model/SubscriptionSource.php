<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A subscription of the member's and who bills it: one that buying here
 * would not stop.
 */
final readonly class SubscriptionSource implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'SubscriptionSource';

    public const string BILLED_BY_SITE = 'site';

    public const string BILLED_BY_APP_STORE = 'app_store';

    public const string BILLED_BY_GOOGLE_PLAY = 'google_play';

    /**
     * @param string $billedBy who bills it, one of the BILLED_BY_ constants
     * @param string|null $manageUrl where the member stops it, or null where the site does
     * @param string $subscriptionId the subscription's id
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $billedBy,
        public ?string $manageUrl,
        public string $subscriptionId,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            billedBy: $json->string('billed_by'),
            manageUrl: $json->nullableString('manage_url'),
            subscriptionId: $json->string('subscription_id'),
            extra: $json->extra(),
        );
    }
}

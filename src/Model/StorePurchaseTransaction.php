<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What the site made of a store purchase it was handed.
 */
final readonly class StorePurchaseTransaction implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'StorePurchaseTransaction';

    public const string OUTCOME_RECORDED = 'recorded';

    public const string OUTCOME_UNCLAIMED = 'unclaimed';

    public const string KIND_SUBSCRIPTION = 'subscription';

    public const string KIND_ONE_TIME = 'one_time';

    public const string KIND_CONSUMABLE = 'consumable';

    public const string ENVIRONMENT_PRODUCTION = 'production';

    public const string ENVIRONMENT_SANDBOX = 'sandbox';

    /**
     * @param string $outcome what the site did with the purchase, one of the OUTCOME_ constants
     * @param string|null $subscriptionId the subscription it started or renewed
     * @param string|null $status that subscription's status
     * @param string|null $kind one of the KIND_ constants
     * @param string|null $environment one of the ENVIRONMENT_ constants
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $outcome,
        public ?string $subscriptionId,
        public ?string $status,
        public ?string $kind,
        public ?string $environment,
        public bool $belongsToYou,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            outcome: $json->string('outcome'),
            belongsToYou: $json->bool('belongs_to_you'),
            subscriptionId: $json->nullableString('subscription_id'),
            status: $json->nullableString('status'),
            kind: $json->nullableString('kind'),
            environment: $json->nullableString('environment'),
            extra: $json->extra(),
        );
    }
}

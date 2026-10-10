<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What the member earns for a referral.
 */
final readonly class ReferralTerms
{
    /** @internal */
    public const string SCHEMA = 'ReferralTerms';

    /**
     * @param int $value in the minor units of $currency
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $type,
        public int $value,
        public string $currency,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            type: $json->string('type'),
            value: $json->int('value'),
            currency: $json->string('currency'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class MemberTrial implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'MemberTrial';

    public const string STATE_ACTIVE = 'active';
    public const string STATE_EXPIRED = 'expired';
    public const string STATE_CONVERTED = 'converted';
    public const string KIND_TRIAL = 'trial';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $state,
        public string $kind,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $expiresAt,
        public bool $paymentMethodRequired,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            state: $json->string('state'),
            kind: $json->string('kind'),
            startsAt: $json->time('starts_at'),
            expiresAt: $json->nullableTime('expires_at'),
            paymentMethodRequired: $json->bool('payment_method_required'),
            extra: $json->extra(),
        );
    }
}

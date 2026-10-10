<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

/**
 * One payment on an order. Book a purchase, and match a refund or a
 * chargeback to it, by `$reference`.
 */
final readonly class Payment
{
    /**
     * @param int $amount in the minor unit of `$currency`
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('reference'),
            $fields->int('amount'),
            $fields->string('currency'),
            $fields->extra(),
        );
    }
}

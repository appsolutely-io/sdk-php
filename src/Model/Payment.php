<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * One payment on an order. Book a purchase, and match a refund or a
 * chargeback to it, by `$reference`.
 */
final readonly class Payment
{
    /**
     * @param int $amount in the minor unit of `$currency`
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            $json->string('reference'),
            $json->int('amount'),
            $json->string('currency'),
            $json->extra(),
        );
    }
}

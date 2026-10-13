<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\CheckoutSession;

/**
 * Hosted checkouts the member starts.
 */
final readonly class CheckoutSessions
{
    /** @internal obtain it from Client::forMember()->api()->me()->checkoutSessions() */
    public function __construct(private Caller $caller) {}

    /**
     * Starts a checkout once, however often the call is retried; a retry
     * with the same key answers the checkout it started.
     *
     * @param non-empty-list<array{product: string, quantity: int<1, 1000>}> $lines each product by slug, and how many
     * @param string $successUrl where the member is sent once paid: one of the app's return targets
     * @param string $cancelUrl where the member is sent on leaving: one of the app's return targets
     * @return KeyedResult<CheckoutSession>
     */
    #[Endpoint(Operation::CreateMeCheckoutSession)]
    public function create(array $lines, string $successUrl, string $cancelUrl, ?string $promotionCode = null, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeCheckoutSession, CheckoutSession::class, [], [
            'lines' => $lines,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'promotion_code' => $promotionCode,
        ], $idempotencyKey);
    }

    #[Endpoint(Operation::GetMeCheckoutSession)]
    public function get(string $id): CheckoutSession
    {
        return $this->caller->read(Operation::GetMeCheckoutSession, CheckoutSession::class, ['session' => $id]);
    }
}

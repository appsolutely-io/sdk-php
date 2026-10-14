<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\CheckoutSession;

/**
 * Hosted checkouts the backend starts for a member it names.
 */
final readonly class CheckoutSessions
{
    /** @internal obtain it from Client::backend()->checkoutSessions() */
    public function __construct(private Caller $caller) {}

    /**
     * Starts a checkout once, however often the call is retried; a retry
     * with the same key answers the checkout it started.
     *
     * A member whose plan order is still waiting for its payment is refused
     * as a ConflictException of type `subscription-awaiting-payment`:
     * `extension('order')` names the waiting order and
     * `extension('payment_url')` where to send the signed-in member to pay
     * it, null when there is nothing to pay on a page.
     *
     * @param string $subject the member's public name, the `sub` its ID token carries
     * @param non-empty-list<array{product: string, quantity: int<1, 1000>, sku?: non-empty-string}> $lines each product by slug, how many, and optionally the sku to buy by the identifier the product lists it under in `skuPrices`; a product sold as several skus needs one
     * @param string $successUrl where the member is sent once paid: one of the backend's return targets
     * @param string $cancelUrl where the member is sent on leaving: one of the backend's return targets
     * @return KeyedResult<CheckoutSession>
     */
    #[Endpoint(Operation::CreateCheckoutSession)]
    public function create(string $subject, array $lines, string $successUrl, string $cancelUrl, ?string $promotionCode = null, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateCheckoutSession, CheckoutSession::class, [], [
            'subject' => $subject,
            'lines' => $lines,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'promotion_code' => $promotionCode,
        ], $idempotencyKey);
    }

    #[Endpoint(Operation::GetCheckoutSession)]
    public function get(string $id): CheckoutSession
    {
        return $this->caller->read(Operation::GetCheckoutSession, CheckoutSession::class, ['session' => $id]);
    }
}

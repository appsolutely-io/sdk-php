<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\MemberSubscription;
use Appsolutely\Sdk\Model\MemberSubscriptionChange;

/**
 * The member's own subscriptions, as the member manages them.
 */
final readonly class Subscriptions
{
    /** @internal obtain it from Client::forMember()->api()->me()->subscriptions() */
    public function __construct(private Caller $caller) {}

    /**
     * Every subscription the member holds, in one page that never has a
     * cursor.
     *
     * @return Page<MemberSubscription>
     */
    #[Endpoint(Operation::ListMeSubscriptions)]
    public function list(): Page
    {
        return $this->caller->onlyPage(Operation::ListMeSubscriptions, MemberSubscription::class);
    }

    /**
     * Stops the subscription at the end of its current period, once however
     * often the call is retried.
     *
     * @return KeyedResult<MemberSubscription>
     */
    #[Endpoint(Operation::CancelMeSubscription)]
    public function cancel(string $id, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CancelMeSubscription, MemberSubscription::class, ['subscription' => $id], [], $idempotencyKey);
    }

    /**
     * Takes back a cancellation not yet in force, or asks the member to
     * confirm it on the site's billing pages.
     *
     * @param string $returnUrl where the site sends the member back to after they confirm it, one of the site's return addresses
     * @return KeyedResult<MemberSubscriptionChange>
     */
    #[Endpoint(Operation::ResumeMeSubscription)]
    public function resume(string $id, string $returnUrl, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::ResumeMeSubscription, MemberSubscriptionChange::class, ['subscription' => $id], ['return_url' => $returnUrl], $idempotencyKey);
    }

    /**
     * Renews the subscription on another plan, or at another quantity: name
     * one of the two. The site applies it at once, or hands it to the member
     * to confirm on its billing pages (a dearer change, or one on another
     * billing interval); which one is not known beforehand, so the return
     * address is always sent.
     *
     * @param string $returnUrl where the site sends the member back to when the change is theirs to confirm, one of the site's return addresses
     * @param string|null $sku the slug of the plan to renew on
     * @param positive-int|null $quantity the units to renew at
     * @return KeyedResult<MemberSubscriptionChange>
     */
    #[Endpoint(Operation::ChangeMeSubscription)]
    public function change(string $id, string $returnUrl, ?string $sku = null, ?int $quantity = null, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::ChangeMeSubscription, MemberSubscriptionChange::class, ['subscription' => $id], ['sku' => $sku, 'quantity' => $quantity, 'return_url' => $returnUrl], $idempotencyKey);
    }
}

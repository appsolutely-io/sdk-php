<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Page;
use Appsolutely\Sdk\Model\Seat;
use Appsolutely\Sdk\Model\SeatPool;

/**
 * The seats of subscriptions a payer bought by quantity, and who holds them.
 */
final readonly class Seats
{
    /** @internal obtain it from Client::backend()->seats() */
    public function __construct(private Caller $caller) {}

    /**
     * Gives a member a seat in one of the payer's pools.
     *
     * @param string $pool the id of the subscription the pool is
     * @param string $payer the payer's public name, the `sub` its ID token carries
     * @param string $member the member's public name
     * @param string|null $label how the payer tells the seat apart
     */
    #[Endpoint(Operation::CreateSeat)]
    public function grant(string $pool, string $payer, string $member, ?string $label = null): Seat
    {
        return $this->caller->read(Operation::CreateSeat, Seat::SCHEMA, Seat::from(...), body: ['pool' => $pool, 'payer' => $payer, 'member' => $member, 'label' => $label]);
    }

    /**
     * Takes back the member's seat in the backend's mode; the same whether
     * or not the member held one.
     *
     * @param string $member the member's public name
     */
    #[Endpoint(Operation::DeleteSeat)]
    public function take(string $member): void
    {
        $this->caller->send(Operation::DeleteSeat, ['sub' => $member]);
    }

    /**
     * The payer's pools and their seats, in one page that never has a cursor.
     *
     * @param string $payer the payer's public name
     * @return Page<SeatPool>
     */
    #[Endpoint(Operation::ListSeatPools)]
    public function pools(string $payer): Page
    {
        return $this->caller->onlyPage(Operation::ListSeatPools, SeatPool::SCHEMA, SeatPool::from(...), path: ['payer' => $payer]);
    }
}

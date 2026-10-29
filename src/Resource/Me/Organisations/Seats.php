<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\OrganisationSeatPool;
use Appsolutely\Sdk\Model\Seat;
use Appsolutely\Sdk\Support\OptionalFields;

/**
 * Organisation seat pools and the members assigned to them.
 */
final readonly class Seats
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return Paginator<OrganisationSeatPool> */
    #[Endpoint(Operation::ListMeOrganisationPools)]
    public function pools(string $organisation, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationPools, OrganisationSeatPool::class, [], $limit, ['organisation' => $organisation]);
    }

    /** @return Paginator<Seat> */
    #[Endpoint(Operation::ListMeOrganisationPoolSeats)]
    public function list(string $organisation, string $pool, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationPoolSeats, Seat::class, [], $limit, ['organisation' => $organisation, 'pool' => $pool]);
    }

    /** @return KeyedResult<Seat> */
    #[Endpoint(Operation::CreateMeOrganisationSeat)]
    public function create(string $organisation, string $pool, string $member, ?string $label = null, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationSeat, Seat::class, ['organisation' => $organisation], OptionalFields::withoutNulls(['pool' => $pool, 'member' => $member, 'label' => $label]), $idempotencyKey);
    }

    #[Endpoint(Operation::DeleteMeOrganisationSeat)]
    public function delete(string $organisation, string $seat, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->caller->send(Operation::DeleteMeOrganisationSeat, ['organisation' => $organisation, 'seat' => $seat], body: [], idempotencyKey: $idempotencyKey);
    }
}

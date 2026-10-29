<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class Seats
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return \Appsolutely\Sdk\Api\Paginator<\Appsolutely\Sdk\Model\OrganisationSeatPool> */
    #[Endpoint(Operation::ListMeOrganisationPools)]
    public function pools(string $organisation, int $limit = \Appsolutely\Sdk\Api\SiteApi::DEFAULT_LIMIT): \Appsolutely\Sdk\Api\Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationPools, \Appsolutely\Sdk\Model\OrganisationSeatPool::class, [], $limit, ['organisation' => $organisation]);
    }

    /** @return \Appsolutely\Sdk\Api\Paginator<\Appsolutely\Sdk\Model\Seat> */
    #[Endpoint(Operation::ListMeOrganisationPoolSeats)]
    public function list(string $organisation, string $pool, int $limit = \Appsolutely\Sdk\Api\SiteApi::DEFAULT_LIMIT): \Appsolutely\Sdk\Api\Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationPoolSeats, \Appsolutely\Sdk\Model\Seat::class, [], $limit, ['organisation' => $organisation, 'pool' => $pool]);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\Seat> */
    #[Endpoint(Operation::CreateMeOrganisationSeat)]
    public function create(string $organisation, string $pool, string $member, ?string $label = null, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationSeat, \Appsolutely\Sdk\Model\Seat::class, ['organisation' => $organisation], array_filter(['pool' => $pool, 'member' => $member, 'label' => $label], static fn(mixed $value): bool => $value !== null), $idempotencyKey);
    }

    #[Endpoint(Operation::DeleteMeOrganisationSeat)]
    public function delete(string $organisation, string $seat, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\ApiResponse
    {
        return $this->caller->send(Operation::DeleteMeOrganisationSeat, ['organisation' => $organisation, 'seat' => $seat], body: [], idempotencyKey: $idempotencyKey);
    }
}

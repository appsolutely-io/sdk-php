<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\Address;

/**
 * The addresses the member has filed.
 *
 * @phpstan-type AddressFields array{name?: string, mobile?: string, address?: string, address_extra?: string, town?: string, city?: string, province?: string, postcode?: string, country?: string, note?: string, remark?: string, sort?: int<0, 255>}
 */
final readonly class Addresses
{
    /** @internal obtain it from Client::forMember()->api()->me()->addresses() */
    public function __construct(private Caller $caller) {}

    /**
     * @param 'asc'|'desc'|null $order
     * @return Paginator<Address>
     */
    #[Endpoint(Operation::ListMeAddresses)]
    public function list(?string $sort = null, ?string $order = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeAddresses, Address::SCHEMA, Address::from(...), ['sort' => $sort, 'order' => $order], $limit);
    }

    /**
     * Files an address once, however often the call is retried: it is sent
     * with an Idempotency-Key, the caller's or a fresh one.
     *
     * @param array{name: string, address: string, mobile?: string, address_extra?: string, town?: string, city?: string, province?: string, postcode?: string, country?: string, note?: string, remark?: string, sort?: int<0, 255>} $address
     * @return KeyedResult<Address>
     */
    #[Endpoint(Operation::CreateMeAddress)]
    public function create(array $address, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeAddress, Address::SCHEMA, Address::from(...), [], $address, $idempotencyKey);
    }

    /**
     * Changes the fields given and leaves the rest.
     *
     * @param AddressFields $changes
     */
    #[Endpoint(Operation::UpdateMeAddress)]
    public function update(string $id, array $changes): Address
    {
        return $this->caller->read(Operation::UpdateMeAddress, Address::SCHEMA, Address::from(...), ['id' => $id], body: $changes);
    }

    #[Endpoint(Operation::DeleteMeAddress)]
    public function delete(string $id): void
    {
        $this->caller->send(Operation::DeleteMeAddress, ['id' => $id]);
    }
}

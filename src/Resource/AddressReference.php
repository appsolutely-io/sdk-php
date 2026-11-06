<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\AddressArea;
use Appsolutely\Sdk\Model\AddressCountry;
use Appsolutely\Sdk\Model\AddressFormat;
use Appsolutely\Sdk\Model\AddressPostalCode;

/**
 * The reference data an address form is built from: the countries, how
 * each writes an address and its postal codes, and the areas it divides
 * itself into. A member and the backend read it alike; the backend's
 * client needs the `reference:read` scope.
 */
final readonly class AddressReference
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /**
     * @param bool|null $includeRetired whether countries no longer offered are listed too
     * @return Paginator<AddressCountry>
     */
    #[Endpoint(Operation::ListAddressCountries)]
    public function countries(?bool $includeRetired = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListAddressCountries, AddressCountry::class, ['include_retired' => $includeRetired], $limit);
    }

    /**
     * @param string|null $country an ISO 3166-1 alpha-2 code, for that country's format only
     * @return Paginator<AddressFormat>
     */
    #[Endpoint(Operation::ListAddressFormats)]
    public function formats(?string $country = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListAddressFormats, AddressFormat::class, ['country' => $country], $limit);
    }

    /**
     * @param string $country an ISO 3166-1 alpha-2 code
     * @param string|null $parent an area's code, for the areas inside it; the top-level areas otherwise
     * @param bool|null $includeRetired whether areas no longer offered are listed too
     * @return Paginator<AddressArea>
     */
    #[Endpoint(Operation::ListAddressAreas)]
    public function areas(string $country, ?string $parent = null, ?bool $includeRetired = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListAddressAreas, AddressArea::class, ['country' => $country, 'parent' => $parent, 'include_retired' => $includeRetired], $limit);
    }

    /**
     * @param string|null $country an ISO 3166-1 alpha-2 code, for that country's postal codes only
     * @return Paginator<AddressPostalCode>
     */
    #[Endpoint(Operation::ListAddressPostalCodes)]
    public function postalCodes(?string $country = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListAddressPostalCodes, AddressPostalCode::class, ['country' => $country], $limit);
    }
}

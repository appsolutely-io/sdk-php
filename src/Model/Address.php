<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An address the member has filed, such as for shipping.
 */
final readonly class Address
{
    /** @internal */
    public const string SCHEMA = 'UserAddress';

    /**
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $mobile,
        public ?string $address,
        public ?string $addressExtra,
        public ?string $town,
        public ?string $city,
        public ?string $province,
        public ?string $postcode,
        public ?string $country,
        public ?string $note,
        public ?string $remark,
        public ?int $sort,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            name: $json->optionalString('name'),
            mobile: $json->optionalString('mobile'),
            address: $json->optionalString('address'),
            addressExtra: $json->optionalString('address_extra'),
            town: $json->optionalString('town'),
            city: $json->optionalString('city'),
            province: $json->optionalString('province'),
            postcode: $json->optionalString('postcode'),
            country: $json->optionalString('country'),
            note: $json->optionalString('note'),
            remark: $json->optionalString('remark'),
            sort: $json->optionalInt('sort'),
            createdAt: $json->optionalTime('created_at'),
            updatedAt: $json->optionalTime('updated_at'),
            attributes: $json->all(),
        );
    }
}

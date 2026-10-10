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
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
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
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            name: $json->nullableString('name'),
            mobile: $json->nullableString('mobile'),
            address: $json->nullableString('address'),
            addressExtra: $json->nullableString('address_extra'),
            town: $json->nullableString('town'),
            city: $json->nullableString('city'),
            province: $json->nullableString('province'),
            postcode: $json->nullableString('postcode'),
            country: $json->nullableString('country'),
            note: $json->nullableString('note'),
            remark: $json->nullableString('remark'),
            sort: $json->nullableInt('sort'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            extra: $json->extra(),
        );
    }
}

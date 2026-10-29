<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrganisationAdmission implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationAdmission';

    public const string ADMISSION_MEMBERSHIP_ONLY = 'membership-only';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $organisation,
        public string $membership,
        public string $admission,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            organisation: $json->string('organisation'),
            membership: $json->string('membership'),
            admission: $json->string('admission'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class OrganisationInvitation implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationInvitation';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $email,
        public DateTimeImmutable $expiresAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            email: $json->string('email'),
            expiresAt: $json->time('expires_at'),
            extra: $json->extra(),
        );
    }
}

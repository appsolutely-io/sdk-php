<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrganisationBillingOperation implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationBillingOperation';

    public const string STATUS_QUEUED = 'queued';
    public const string STATUS_RUNNING = 'running';
    public const string STATUS_SUCCEEDED = 'succeeded';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_CANCELLED = 'cancelled';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $status,
        public ?string $detail,
        public ?string $binding,
        public ?string $confirmationUrl,
        public ?string $setup,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            status: $json->string('status'),
            detail: $json->nullableString('detail'),
            binding: $json->nullableString('binding'),
            confirmationUrl: $json->nullableString('confirmation_url'),
            setup: $json->nullableString('setup'),
            extra: $json->extra(),
        );
    }
}

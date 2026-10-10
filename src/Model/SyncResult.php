<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use Closure;

/**
 * How the site answered one mutation of a sync push.
 *
 * @template-covariant T
 */
final readonly class SyncResult
{
    /**
     * The schemas of the document this is read from, one per resource.
     *
     * @internal
     */
    public const array SCHEMAS = ['ArticleSyncResult', 'UserAddressSyncResult'];

    /**
     * @param string $mutationId the id the mutation was sent with
     * @param string|null $id the record the mutation applied to
     * @param bool $applied whether the mutation changed the record
     * @param string|null $reason why it was not applied
     * @param array<string, mixed>|null $errors the validation messages, keyed by field
     * @param T|null $serverRecord the record as the site now holds it
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $mutationId,
        public ?string $id,
        public bool $applied,
        public ?string $reason,
        public ?array $errors,
        public mixed $serverRecord,
        public array $extra = [],
    ) {}

    /**
     * @internal
     *
     * @template U
     *
     * @param Closure(Fields): U $record reads one record of the resource
     * @return self<U>
     */
    public static function from(#[\SensitiveParameter] Fields $json, Closure $record): self
    {
        $serverRecord = $json->nullableObject('server_record');

        return new self(
            mutationId: $json->string('mutation_id'),
            id: $json->nullableString('id'),
            applied: $json->bool('applied'),
            reason: $json->nullableString('reason'),
            errors: $json->nullableMap('errors'),
            serverRecord: $serverRecord === null ? null : $record($serverRecord),
            extra: $json->extra(),
        );
    }
}

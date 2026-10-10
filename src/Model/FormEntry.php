<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One submission of one of the site's forms, as an API answer and a
 * `form.submitted` delivery both carry it.
 */
final readonly class FormEntry
{
    /** @internal */
    public const string SCHEMA = 'FormEntry';

    /**
     * @param array<array-key, mixed>|null $data the values submitted, keyed by field name
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $formSlug,
        public ?string $name,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $mobile,
        public ?array $data,
        public bool $isSpam,
        public DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            formSlug: $json->nullableString('form_slug'),
            name: $json->nullableString('name'),
            firstName: $json->nullableString('first_name'),
            lastName: $json->nullableString('last_name'),
            email: $json->nullableString('email'),
            mobile: $json->nullableString('mobile'),
            data: $json->nullableMap('data'),
            isSpam: $json->bool('is_spam'),
            submittedAt: $json->time('submitted_at'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            extra: $json->extra(),
        );
    }
}

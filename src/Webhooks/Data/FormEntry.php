<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * A form entry as the site's REST API serves it.
 */
final readonly class FormEntry
{
    /**
     * @param array<string, mixed>|null $data the values submitted, keyed by field name
     * @param array<string, mixed> $extra fields the site sent that this class does not name
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

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('id'),
            $fields->nullableString('form_slug'),
            $fields->nullableString('name'),
            $fields->nullableString('first_name'),
            $fields->nullableString('last_name'),
            $fields->nullableString('email'),
            $fields->nullableString('mobile'),
            $fields->nullableMap('data'),
            $fields->bool('is_spam'),
            $fields->time('submitted_at'),
            $fields->nullableTime('created_at'),
            $fields->nullableTime('updated_at'),
            $fields->extra(),
        );
    }
}

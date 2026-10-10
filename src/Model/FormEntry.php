<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One submission of one of the site's forms.
 */
final readonly class FormEntry
{
    /** @internal */
    public const string SCHEMA = 'FormEntry';

    /**
     * @param array<string, mixed>|null $data the answers, keyed by field
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
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
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            formSlug: $json->optionalString('form_slug'),
            name: $json->optionalString('name'),
            firstName: $json->optionalString('first_name'),
            lastName: $json->optionalString('last_name'),
            email: $json->optionalString('email'),
            mobile: $json->optionalString('mobile'),
            data: $json->optionalMap('data'),
            isSpam: $json->bool('is_spam'),
            submittedAt: $json->time('submitted_at'),
            createdAt: $json->optionalTime('created_at'),
            updatedAt: $json->optionalTime('updated_at'),
            attributes: $json->all(),
        );
    }
}

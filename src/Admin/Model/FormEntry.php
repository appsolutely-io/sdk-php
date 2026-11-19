<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Model;

use Appsolutely\Sdk\Model\Fields;
use DateTimeImmutable;

/**
 * A form entry as the Administrator API answers it.
 */
final readonly class FormEntry implements AdminModel
{
    /** @internal */
    public const string SCHEMA = 'FormEntry';

    public const int SPAM = 1;
    public const int NOT_SPAM = 0;

    /**
     * @param ?int $isSpam `SPAM` or `NOT_SPAM`
     * @param array<array-key, mixed>|null $data the submitted fields as a decoded object or list
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $formName,
        public ?string $fullName,
        public ?string $email,
        public ?string $mobile,
        public ?string $userName,
        public ?int $isSpam,
        public ?DateTimeImmutable $submittedAt,
        public ?array $data,
        public ?string $referer,
        public ?string $userAgent,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): static
    {
        $data = $json->any('data');
        if ($data !== null && !is_array($data)) {
            throw new \Appsolutely\Sdk\Exception\UnexpectedResponseException('FormEntry.data must be an object, a list or null.', 0, self::SCHEMA);
        }

        return new self(
            id: $json->string('id'),
            formName: $json->nullableString('form_name'),
            fullName: $json->nullableString('full_name'),
            email: $json->nullableString('email'),
            mobile: $json->nullableString('mobile'),
            userName: $json->nullableString('user_name'),
            isSpam: $json->nullableInt('is_spam'),
            submittedAt: $json->nullableTime('submitted_at'),
            data: $data,
            referer: $json->nullableString('referer'),
            userAgent: $json->nullableString('user_agent'),
            extra: $json->extra(),
        );
    }
}

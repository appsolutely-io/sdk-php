<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The site's current terms and whether the member has accepted them. A member
 * who has not is asked to in the site's member area; the Site API stays open
 * to them either way.
 */
final readonly class MemberTerms
{
    /**
     * @param ?string $current the version of the terms the site asks members to accept, or null when it asks for none
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public ?string $current,
        public bool $accepted,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self($json->nullableString('current'), $json->bool('accepted'), $json->extra());
    }
}

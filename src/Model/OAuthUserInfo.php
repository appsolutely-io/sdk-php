<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OAuthUserInfo implements SchemaModel
{
    public const string SCHEMA = 'OAuthUserInfo';

    /**
     * @param list<string>|null $roles
     * @param list<OAuthUserInfoEntitlement>|null $entitlements
     * @param array<array-key, OAuthEntitlementValue>|null $entitlementValues
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $sub,
        public ?string $name,
        public ?string $picture,
        public ?int $updatedAt,
        public ?string $email,
        public ?bool $emailVerified,
        public ?array $roles,
        public ?array $entitlements,
        public ?array $entitlementValues,
        public array $extra = [],
    ) {}

    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $entitlements = $json->nullableObjects('entitlements');
        $values = $json->nullableMap('entitlement_values');
        $entries = null;
        if ($values !== null) {
            $entries = [];
            foreach ($values as $name => $value) {
                if (!is_array($value) || ($value !== [] && array_is_list($value))) {
                    throw new \Appsolutely\Sdk\Exception\UnexpectedResponseException('An entitlement value must be an object.', 0, self::SCHEMA);
                }
                $entries[$name] = OAuthEntitlementValue::from($value);
            }
        }

        return new self($json->string('sub'), $json->nullableString('name'), $json->nullableString('picture'), $json->nullableInt('updated_at'), $json->nullableString('email'), $json->nullableBool('email_verified'), $json->nullableStrings('roles'), $entitlements === null ? null : array_map(OAuthUserInfoEntitlement::from(...), $entitlements), $entries, $json->extra());
    }
}

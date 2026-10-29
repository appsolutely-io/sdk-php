<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OAuthEntitlementValue
{
    public const string KIND_ABSENT = 'absent';
    public const string KIND_BOOLEAN = 'boolean';
    public const string KIND_QUANTITY = 'quantity';
    public const string KIND_CONFIG = 'config';

    /**
     * @param array<array-key, mixed>|bool|int|null $value
     * @param array<array-key, mixed> $extra
     */
    public function __construct(public string $kind, public array|bool|int|null $value, public array $extra = []) {}

    /** @param array<array-key, mixed> $data */
    public static function from(#[\SensitiveParameter] array $data): self
    {
        $json = Fields::of($data, 'OAuthUserInfo.entitlement_values');
        $value = $json->any('value');
        if (!array_key_exists('value', $data) || (!is_array($value) && !is_bool($value) && !is_int($value) && $value !== null)) {
            throw new \Appsolutely\Sdk\Exception\UnexpectedResponseException('The entitlement value has an unexpected shape.', 0, 'OAuthUserInfo');
        }

        return new self($json->string('kind'), $value, $json->extra());
    }
}

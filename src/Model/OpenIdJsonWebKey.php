<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OpenIdJsonWebKey implements SchemaModel
{
    public const string SCHEMA = 'OpenIdJsonWebKey';
    public const string KTY_RSA = 'RSA';
    public const string KTY_EC = 'EC';
    public const string USE_SIG = 'sig';
    public const string ALG_RS256 = 'RS256';
    public const string ALG_ES256 = 'ES256';
    public const string CRV_P_256 = 'P-256';

    /** @param array<array-key, mixed> $extra */
    public function __construct(
        public string $kty,
        public string $use,
        public string $alg,
        public string $kid,
        public ?string $n,
        public ?string $e,
        public ?string $crv,
        public ?string $x,
        public ?string $y,
        public array $extra = [],
    ) {}

    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $kty = $json->string('kty');
        if ($kty !== self::KTY_RSA && $kty !== self::KTY_EC) {
            throw new \Appsolutely\Sdk\Exception\UnexpectedResponseException('The signing key has an unsupported shape.', 0, self::SCHEMA);
        }

        return new self(
            $kty,
            $json->string('use'),
            $json->string('alg'),
            $json->string('kid'),
            $kty === self::KTY_RSA ? $json->string('n') : null,
            $kty === self::KTY_RSA ? $json->string('e') : null,
            $kty === self::KTY_EC ? $json->string('crv') : null,
            $kty === self::KTY_EC ? $json->string('x') : null,
            $kty === self::KTY_EC ? $json->string('y') : null,
            $json->extra(),
        );
    }
}

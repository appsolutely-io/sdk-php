<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The machine client a backend token was granted to, as the site sees it.
 */
final readonly class BackendClient implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'BackendClient';

    public const string MODE_PRODUCTION = 'production';

    public const string MODE_TEST = 'test';

    /**
     * @param string $clientId the machine client's id
     * @param string $mode one of the MODE_ constants: the money its calls act on
     * @param string $site the site the client belongs to
     * @param list<string> $scopes what the token may do
     * @param string $storeCurrency the ISO 4217 currency every checkout on the site is priced and paid in, so prices can be shown in it before a purchase starts
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $clientId,
        public string $mode,
        public string $site,
        public array $scopes,
        public string $storeCurrency,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            clientId: $json->string('client_id'),
            mode: $json->string('mode'),
            site: $json->string('site'),
            scopes: $json->strings('scopes'),
            storeCurrency: $json->string('store_currency'),
            extra: $json->extra(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The machine client a backend token was granted to, as the site sees it.
 */
final readonly class BackendClient
{
    /** @internal */
    public const string SCHEMA = 'BackendClient';

    /**
     * @param string $clientId the machine client's id
     * @param string $mode `production` or `test`: the money its calls act on
     * @param string $site the site the client belongs to
     * @param list<string> $scopes what the token may do
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $clientId,
        public string $mode,
        public string $site,
        public array $scopes,
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
            extra: $json->extra(),
        );
    }
}

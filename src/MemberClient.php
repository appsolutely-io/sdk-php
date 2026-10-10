<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Exception\NotSerializableException;

/**
 * The client acting as one signed-in member: every call carries the access
 * token the member's sign-in returned. Obtain it from Client::forMember().
 */
final readonly class MemberClient
{
    /**
     * @internal obtain it from Client::forMember()
     */
    public function __construct(private SiteApi $api) {}

    public function api(): SiteApi
    {
        return $this->api;
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('A MemberClient holds the member\'s access token and is not serialized; obtain it again from Client::forMember().');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('A MemberClient holds the member\'s access token and is not unserialized; obtain it again from Client::forMember().');
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\BackendMemberMessage;
use Appsolutely\Sdk\Support\OptionalFields;

final readonly class MemberMessages
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /**
     * @param array<string, mixed>|null $data
     * @param list<string>|null $channels
     * @return KeyedResult<BackendMemberMessage>
     */
    #[Endpoint(Operation::CreateBackendMemberMessage)]
    public function create(string $sub, string $category, string $title, string $body, ?string $link = null, #[\SensitiveParameter] ?array $data = null, ?array $channels = null, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateBackendMemberMessage, BackendMemberMessage::class, ['sub' => $sub], ['category' => $category, 'title' => $title, 'body' => $body, ...OptionalFields::withoutNulls(['link' => $link, 'data' => $data, 'channels' => $channels])], $idempotencyKey);
    }
}

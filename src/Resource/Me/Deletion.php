<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\MemberDeletionAccepted;
use Appsolutely\Sdk\Model\MemberDeletionStatus;

final readonly class Deletion
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetMeDeletion)]
    public function get(): MemberDeletionStatus
    {
        return $this->caller->read(Operation::GetMeDeletion, MemberDeletionStatus::class);
    }

    /** @return KeyedResult<MemberDeletionAccepted> */
    #[Endpoint(Operation::CreateMeDeletion)]
    public function create(?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeDeletion, MemberDeletionAccepted::class, [], null, $idempotencyKey);
    }
}

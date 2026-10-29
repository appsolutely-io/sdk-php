<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class RecoveryBindings
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::CreateMeOrganisationRecoveryBinding)]
    public function create(string $organisation, string $pool, string $revision, string $successUrl, string $cancelUrl, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationRecoveryBinding, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation], ['pool' => $pool, 'revision' => $revision, 'success_url' => $successUrl, 'cancel_url' => $cancelUrl], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::CancelMeOrganisationRecoveryBinding)]
    public function cancel(string $organisation, string $binding, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CancelMeOrganisationRecoveryBinding, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'binding' => $binding], [], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::ReconcileMeOrganisationRecoveryBinding)]
    public function reconcile(string $organisation, string $binding, string $revision, #[\SensitiveParameter] ?string $sessionId = null, #[\SensitiveParameter] ?string $sessionResult = null, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::ReconcileMeOrganisationRecoveryBinding, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'binding' => $binding], array_filter(['revision' => $revision, 'session_id' => $sessionId, 'session_result' => $sessionResult], static fn(mixed $value): bool => $value !== null), $idempotencyKey);
    }
}

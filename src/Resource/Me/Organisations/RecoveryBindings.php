<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\OrganisationBillingOperation;
use Appsolutely\Sdk\Support\OptionalFields;

/**
 * Payment bindings used to recover organisation billing.
 */
final readonly class RecoveryBindings
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::CreateMeOrganisationRecoveryBinding)]
    public function create(string $organisation, string $pool, string $revision, string $successUrl, string $cancelUrl, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationRecoveryBinding, OrganisationBillingOperation::class, ['organisation' => $organisation], ['pool' => $pool, 'revision' => $revision, 'success_url' => $successUrl, 'cancel_url' => $cancelUrl], $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::CancelMeOrganisationRecoveryBinding)]
    public function cancel(string $organisation, string $binding, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CancelMeOrganisationRecoveryBinding, OrganisationBillingOperation::class, ['organisation' => $organisation, 'binding' => $binding], [], $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::ReconcileMeOrganisationRecoveryBinding)]
    public function reconcile(string $organisation, string $binding, string $revision, #[\SensitiveParameter] ?string $sessionId = null, #[\SensitiveParameter] ?string $sessionResult = null, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::ReconcileMeOrganisationRecoveryBinding, OrganisationBillingOperation::class, ['organisation' => $organisation, 'binding' => $binding], OptionalFields::withoutNulls(['revision' => $revision, 'session_id' => $sessionId, 'session_result' => $sessionResult]), $idempotencyKey);
    }
}

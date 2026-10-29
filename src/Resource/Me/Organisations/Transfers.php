<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\OrganisationBillingOperation;
use Appsolutely\Sdk\Model\OrganisationBillingReview;
use Appsolutely\Sdk\Model\OrganisationTransfer;
use Appsolutely\Sdk\Support\OptionalFields;

/**
 * Organisation ownership transfer reviews and requests.
 */
final readonly class Transfers
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return KeyedResult<OrganisationTransfer> */
    #[Endpoint(Operation::CreateMeOrganisationTransfer)]
    public function create(string $organisation, string $recipient, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationTransfer, OrganisationTransfer::class, ['organisation' => $organisation], ['recipient' => $recipient], $idempotencyKey);
    }

    #[Endpoint(Operation::GetMeOrganisationTransfer)]
    public function get(string $organisation, string $transfer): OrganisationTransfer
    {
        return $this->caller->read(Operation::GetMeOrganisationTransfer, OrganisationTransfer::class, ['organisation' => $organisation, 'transfer' => $transfer]);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::AcceptMeOrganisationTransfer)]
    public function accept(string $organisation, string $transfer, string $revision, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::AcceptMeOrganisationTransfer, OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer], ['revision' => $revision], $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::CreateMeOrganisationBinding)]
    public function bind(string $organisation, string $transfer, string $pool, string $revision, string $successUrl, string $cancelUrl, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationBinding, OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer], ['pool' => $pool, 'revision' => $revision, 'success_url' => $successUrl, 'cancel_url' => $cancelUrl], $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::ReconcileMeOrganisationBinding)]
    public function reconcile(string $organisation, string $transfer, string $binding, string $revision, #[\SensitiveParameter] ?string $sessionId = null, #[\SensitiveParameter] ?string $sessionResult = null, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::ReconcileMeOrganisationBinding, OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer, 'binding' => $binding], OptionalFields::withoutNulls(['revision' => $revision, 'session_id' => $sessionId, 'session_result' => $sessionResult]), $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::CancelMeOrganisationTransfer)]
    public function cancel(string $organisation, string $transfer, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CancelMeOrganisationTransfer, OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer], [], $idempotencyKey);
    }

    #[Endpoint(Operation::ReviewMeOrganisationTransfer)]
    public function review(string $organisation, string $transfer): OrganisationBillingReview
    {
        return $this->caller->read(Operation::ReviewMeOrganisationTransfer, OrganisationBillingReview::class, ['organisation' => $organisation, 'transfer' => $transfer]);
    }
}

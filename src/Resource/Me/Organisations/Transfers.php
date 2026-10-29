<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class Transfers
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationTransfer> */
    #[Endpoint(Operation::CreateMeOrganisationTransfer)]
    public function create(string $organisation, string $recipient, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationTransfer, \Appsolutely\Sdk\Model\OrganisationTransfer::class, ['organisation' => $organisation], ['recipient' => $recipient], $idempotencyKey);
    }

    #[Endpoint(Operation::GetMeOrganisationTransfer)]
    public function get(string $organisation, string $transfer): \Appsolutely\Sdk\Model\OrganisationTransfer
    {
        return $this->caller->read(Operation::GetMeOrganisationTransfer, \Appsolutely\Sdk\Model\OrganisationTransfer::class, ['organisation' => $organisation, 'transfer' => $transfer]);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::AcceptMeOrganisationTransfer)]
    public function accept(string $organisation, string $transfer, string $revision, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::AcceptMeOrganisationTransfer, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer], ['revision' => $revision], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::CreateMeOrganisationBinding)]
    public function bind(string $organisation, string $transfer, string $pool, string $revision, string $successUrl, string $cancelUrl, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationBinding, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer], ['pool' => $pool, 'revision' => $revision, 'success_url' => $successUrl, 'cancel_url' => $cancelUrl], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::ReconcileMeOrganisationBinding)]
    public function reconcile(string $organisation, string $transfer, string $binding, string $revision, #[\SensitiveParameter] ?string $sessionId = null, #[\SensitiveParameter] ?string $sessionResult = null, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::ReconcileMeOrganisationBinding, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer, 'binding' => $binding], array_filter(['revision' => $revision, 'session_id' => $sessionId, 'session_result' => $sessionResult], static fn(mixed $value): bool => $value !== null), $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::CancelMeOrganisationTransfer)]
    public function cancel(string $organisation, string $transfer, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CancelMeOrganisationTransfer, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'transfer' => $transfer], [], $idempotencyKey);
    }

    #[Endpoint(Operation::ReviewMeOrganisationTransfer)]
    public function review(string $organisation, string $transfer): \Appsolutely\Sdk\Model\OrganisationBillingReview
    {
        return $this->caller->read(Operation::ReviewMeOrganisationTransfer, \Appsolutely\Sdk\Model\OrganisationBillingReview::class, ['organisation' => $organisation, 'transfer' => $transfer]);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class Replacements
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::CreateMeOrganisationReplacement)]
    public function create(string $organisation, string $pool, string $revision, string $successUrl, string $cancelUrl, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationReplacement, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation], ['pool' => $pool, 'revision' => $revision, 'success_url' => $successUrl, 'cancel_url' => $cancelUrl], $idempotencyKey);
    }

    #[Endpoint(Operation::CurrentMeOrganisationReplacement)]
    public function current(string $organisation, string $pool): \Appsolutely\Sdk\Model\OrganisationReplacementSetup
    {
        return $this->caller->read(Operation::CurrentMeOrganisationReplacement, \Appsolutely\Sdk\Model\OrganisationReplacementSetup::class, ['organisation' => $organisation, 'pool' => $pool]);
    }

    #[Endpoint(Operation::ReviewMeOrganisationReplacement)]
    public function review(string $organisation, string $pool): \Appsolutely\Sdk\Model\OrganisationReplacementReview
    {
        return $this->caller->read(Operation::ReviewMeOrganisationReplacement, \Appsolutely\Sdk\Model\OrganisationReplacementReview::class, ['organisation' => $organisation, 'pool' => $pool]);
    }

    #[Endpoint(Operation::GetMeOrganisationReplacement)]
    public function get(string $organisation, string $setup): \Appsolutely\Sdk\Model\OrganisationReplacementSetup
    {
        return $this->caller->read(Operation::GetMeOrganisationReplacement, \Appsolutely\Sdk\Model\OrganisationReplacementSetup::class, ['organisation' => $organisation, 'setup' => $setup]);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::CloseMeOrganisationReplacement)]
    public function close(string $organisation, string $setup, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CloseMeOrganisationReplacement, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'setup' => $setup], [], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::CompleteMeOrganisationReplacement)]
    public function complete(string $organisation, string $setup, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CompleteMeOrganisationReplacement, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'setup' => $setup], [], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::ReconcileMeOrganisationReplacement)]
    public function reconcile(string $organisation, string $setup, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::ReconcileMeOrganisationReplacement, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'setup' => $setup], [], $idempotencyKey);
    }
}

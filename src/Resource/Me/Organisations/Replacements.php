<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\OrganisationBillingOperation;
use Appsolutely\Sdk\Model\OrganisationReplacementReview;
use Appsolutely\Sdk\Model\OrganisationReplacementSetup;

/**
 * Reviewed organisation plan replacements and their setup state.
 */
final readonly class Replacements
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::CreateMeOrganisationReplacement)]
    public function create(string $organisation, string $pool, string $revision, string $successUrl, string $cancelUrl, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationReplacement, OrganisationBillingOperation::class, ['organisation' => $organisation], ['pool' => $pool, 'revision' => $revision, 'success_url' => $successUrl, 'cancel_url' => $cancelUrl], $idempotencyKey);
    }

    #[Endpoint(Operation::CurrentMeOrganisationReplacement)]
    public function current(string $organisation, string $pool): OrganisationReplacementSetup
    {
        return $this->caller->read(Operation::CurrentMeOrganisationReplacement, OrganisationReplacementSetup::class, ['organisation' => $organisation, 'pool' => $pool]);
    }

    #[Endpoint(Operation::ReviewMeOrganisationReplacement)]
    public function review(string $organisation, string $pool): OrganisationReplacementReview
    {
        return $this->caller->read(Operation::ReviewMeOrganisationReplacement, OrganisationReplacementReview::class, ['organisation' => $organisation, 'pool' => $pool]);
    }

    #[Endpoint(Operation::GetMeOrganisationReplacement)]
    public function get(string $organisation, string $setup): OrganisationReplacementSetup
    {
        return $this->caller->read(Operation::GetMeOrganisationReplacement, OrganisationReplacementSetup::class, ['organisation' => $organisation, 'setup' => $setup]);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::CloseMeOrganisationReplacement)]
    public function close(string $organisation, string $setup, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CloseMeOrganisationReplacement, OrganisationBillingOperation::class, ['organisation' => $organisation, 'setup' => $setup], [], $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::CompleteMeOrganisationReplacement)]
    public function complete(string $organisation, string $setup, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CompleteMeOrganisationReplacement, OrganisationBillingOperation::class, ['organisation' => $organisation, 'setup' => $setup], [], $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::ReconcileMeOrganisationReplacement)]
    public function reconcile(string $organisation, string $setup, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::ReconcileMeOrganisationReplacement, OrganisationBillingOperation::class, ['organisation' => $organisation, 'setup' => $setup], [], $idempotencyKey);
    }
}

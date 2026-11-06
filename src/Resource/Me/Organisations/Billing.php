<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\OrganisationBillingOperation;
use Appsolutely\Sdk\Model\OrganisationBillingReview;

/**
 * Reviewed billing requests and the server's operation outcomes.
 */
final readonly class Billing
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetMeOrganisationBillingOperation)]
    public function operation(string $organisation, string $operation): OrganisationBillingOperation
    {
        return $this->caller->read(Operation::GetMeOrganisationBillingOperation, OrganisationBillingOperation::class, ['organisation' => $organisation, 'operation' => $operation]);
    }

    #[Endpoint(Operation::ReviewMeOrganisationBilling)]
    public function review(string $organisation): OrganisationBillingReview
    {
        return $this->caller->read(Operation::ReviewMeOrganisationBilling, OrganisationBillingReview::class, ['organisation' => $organisation]);
    }

    /** @return KeyedResult<OrganisationBillingOperation> */
    #[Endpoint(Operation::TakeoverMeOrganisationBilling)]
    public function takeover(string $organisation, string $revision, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::TakeoverMeOrganisationBilling, OrganisationBillingOperation::class, ['organisation' => $organisation], ['revision' => $revision], $idempotencyKey);
    }
}

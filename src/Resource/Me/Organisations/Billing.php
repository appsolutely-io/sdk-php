<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class Billing
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetMeOrganisationBillingOperation)]
    public function operation(string $organisation, string $operation): \Appsolutely\Sdk\Model\OrganisationBillingOperation
    {
        return $this->caller->read(Operation::GetMeOrganisationBillingOperation, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation, 'operation' => $operation]);
    }

    #[Endpoint(Operation::ReviewMeOrganisationBilling)]
    public function review(string $organisation): \Appsolutely\Sdk\Model\OrganisationBillingReview
    {
        return $this->caller->read(Operation::ReviewMeOrganisationBilling, \Appsolutely\Sdk\Model\OrganisationBillingReview::class, ['organisation' => $organisation]);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationBillingOperation> */
    #[Endpoint(Operation::TakeoverMeOrganisationBilling)]
    public function takeover(string $organisation, string $revision, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::TakeoverMeOrganisationBilling, \Appsolutely\Sdk\Model\OrganisationBillingOperation::class, ['organisation' => $organisation], ['revision' => $revision], $idempotencyKey);
    }
}

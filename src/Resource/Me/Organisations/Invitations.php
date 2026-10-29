<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;

final readonly class Invitations
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return \Appsolutely\Sdk\Api\Paginator<\Appsolutely\Sdk\Model\OrganisationInvitation> */
    #[Endpoint(Operation::ListMeOrganisationInvitations)]
    public function list(string $organisation, int $limit = \Appsolutely\Sdk\Api\SiteApi::DEFAULT_LIMIT): \Appsolutely\Sdk\Api\Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationInvitations, \Appsolutely\Sdk\Model\OrganisationInvitation::class, [], $limit, ['organisation' => $organisation]);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationInvitation> */
    #[Endpoint(Operation::CreateMeOrganisationInvitation)]
    public function create(string $organisation, #[\SensitiveParameter] string $email, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationInvitation, \Appsolutely\Sdk\Model\OrganisationInvitation::class, ['organisation' => $organisation], ['email' => $email], $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationAdmission> */
    #[Endpoint(Operation::AcceptMeOrganisationInvitation)]
    public function accept(string $organisation, string $invitation, #[\SensitiveParameter] string $token, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::AcceptMeOrganisationInvitation, \Appsolutely\Sdk\Model\OrganisationAdmission::class, ['organisation' => $organisation, 'invitation' => $invitation], ['token' => $token], $idempotencyKey);
    }

    #[Endpoint(Operation::DeclineMeOrganisationInvitation)]
    public function decline(string $organisation, string $invitation, #[\SensitiveParameter] string $token, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\ApiResponse
    {
        return $this->caller->send(Operation::DeclineMeOrganisationInvitation, ['organisation' => $organisation, 'invitation' => $invitation], body: ['token' => $token], idempotencyKey: $idempotencyKey);
    }

    /** @return \Appsolutely\Sdk\Api\KeyedResult<\Appsolutely\Sdk\Model\OrganisationInvitation> */
    #[Endpoint(Operation::ResendMeOrganisationInvitation)]
    public function resend(string $organisation, string $invitation, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\KeyedResult
    {
        return $this->caller->keyed(Operation::ResendMeOrganisationInvitation, \Appsolutely\Sdk\Model\OrganisationInvitation::class, ['organisation' => $organisation, 'invitation' => $invitation], [], $idempotencyKey);
    }

    #[Endpoint(Operation::RevokeMeOrganisationInvitation)]
    public function revoke(string $organisation, string $invitation, ?string $idempotencyKey = null): \Appsolutely\Sdk\Api\ApiResponse
    {
        return $this->caller->send(Operation::RevokeMeOrganisationInvitation, ['organisation' => $organisation, 'invitation' => $invitation], body: [], idempotencyKey: $idempotencyKey);
    }
}

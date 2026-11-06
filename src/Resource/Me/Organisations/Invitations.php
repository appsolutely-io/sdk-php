<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me\Organisations;

use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\OrganisationAdmission;
use Appsolutely\Sdk\Model\OrganisationInvitation;

/**
 * Organisation invitations and the member's responses to them.
 */
final readonly class Invitations
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    /** @return Paginator<OrganisationInvitation> */
    #[Endpoint(Operation::ListMeOrganisationInvitations)]
    public function list(string $organisation, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListMeOrganisationInvitations, OrganisationInvitation::class, [], $limit, ['organisation' => $organisation]);
    }

    /** @return KeyedResult<OrganisationInvitation> */
    #[Endpoint(Operation::CreateMeOrganisationInvitation)]
    public function create(string $organisation, #[\SensitiveParameter] string $email, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMeOrganisationInvitation, OrganisationInvitation::class, ['organisation' => $organisation], ['email' => $email], $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationAdmission> */
    #[Endpoint(Operation::AcceptMeOrganisationInvitation)]
    public function accept(string $organisation, string $invitation, #[\SensitiveParameter] string $token, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::AcceptMeOrganisationInvitation, OrganisationAdmission::class, ['organisation' => $organisation, 'invitation' => $invitation], ['token' => $token], $idempotencyKey);
    }

    #[Endpoint(Operation::DeclineMeOrganisationInvitation)]
    public function decline(string $organisation, string $invitation, #[\SensitiveParameter] string $token, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->caller->send(Operation::DeclineMeOrganisationInvitation, ['organisation' => $organisation, 'invitation' => $invitation], body: ['token' => $token], idempotencyKey: $idempotencyKey);
    }

    /** @return KeyedResult<OrganisationInvitation> */
    #[Endpoint(Operation::ResendMeOrganisationInvitation)]
    public function resend(string $organisation, string $invitation, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::ResendMeOrganisationInvitation, OrganisationInvitation::class, ['organisation' => $organisation, 'invitation' => $invitation], [], $idempotencyKey);
    }

    #[Endpoint(Operation::RevokeMeOrganisationInvitation)]
    public function revoke(string $organisation, string $invitation, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->caller->send(Operation::RevokeMeOrganisationInvitation, ['organisation' => $organisation, 'invitation' => $invitation], body: [], idempotencyKey: $idempotencyKey);
    }
}

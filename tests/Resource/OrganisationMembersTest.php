<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\OrganisationDissolutionPool;
use Appsolutely\Sdk\Model\OrganisationDissolutionPreview;
use Appsolutely\Sdk\Model\OrganisationDissolutionProgress;
use Appsolutely\Sdk\Model\OrganisationMembership;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OrganisationMembersTest extends TestCase
{
    public function testMembersAreListedWithTheirRolesAndAdminSince(): void
    {
        $fake = (new FakeClient())->answerPage(Operation::ListMeOrganisationMembers, [
            ['membership' => 'm-1', 'subject' => 'sub-1', 'role' => 'admin', 'joined_at' => '2026-01-02T03:04:05Z', 'admin_since' => '2026-02-01T00:00:00Z'],
            ['membership' => 'm-2', 'role' => 'member', 'joined_at' => '2026-01-03T00:00:00Z', 'admin_since' => null],
        ]);

        $members = iterator_to_array($fake->forMember()->api()->me()->organisations()->members()->list('org/1'), false);

        self::assertCount(2, $members);
        self::assertSame(OrganisationMembership::ROLE_ADMIN, $members[0]->role);
        self::assertSame('2026-02-01T00:00:00+00:00', $members[0]->adminSince?->format(DATE_ATOM));
        self::assertNull($members[1]->subject);
        self::assertNull($members[1]->adminSince);
        self::assertSame(['organisation' => 'org/1'], $fake->lastCall(Operation::ListMeOrganisationMembers)->pathParameters);
    }

    public function testRemovingPromotingAndDemotingKeepTheirVerbsKeysAndSendNoBody(): void
    {
        $site = (new SiteRecorder())->empty(204)->empty(204)->empty(204);
        $members = $site->provider->client()->forMember('member-token')->api()->me()->organisations()->members();

        self::assertSame(204, $members->promote('org', 'm-1', 'promote-key')->status);
        self::assertSame(204, $members->demote('org', 'm-1', 'demote-key')->status);
        self::assertSame(204, $members->remove('org', 'm-1', 'remove-key')->status);

        self::assertSame('PUT /api/v1/me/organisations/org/members/m-1/admin', $site->line(0));
        self::assertSame('DELETE /api/v1/me/organisations/org/members/m-1/admin', $site->line(1));
        self::assertSame('DELETE /api/v1/me/organisations/org/members/m-1', $site->line(2));
        foreach ([0 => 'promote-key', 1 => 'demote-key', 2 => 'remove-key'] as $index => $key) {
            self::assertSame('"' . $key . '"', $site->request($index)->getHeaderLine('Idempotency-Key'));
            self::assertSame('', (string) $site->request($index)->getBody());
        }
    }

    public function testADissolutionNotYetStartedIsReadAsItsPreview(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetMeOrganisationDissolution, [
            'revision' => 'rev-1',
            'consequence' => ['refund' => 'none', 'seats_end' => 'immediately'],
            'pools' => [['id' => 'pool-1', 'plan' => 'team', 'quantity' => 5, 'occupied' => 2, 'paid_until' => null, 'stop' => 'provider_immediate']],
            'blockers' => ['pending_order'],
            'invitations_pending' => 1,
            'members_active' => 3,
        ]);

        $dissolution = $fake->forMember()->api()->me()->organisations()->dissolution('org');

        self::assertNull($dissolution->progress);
        self::assertInstanceOf(OrganisationDissolutionPreview::class, $dissolution->preview);
        self::assertSame([OrganisationDissolutionPreview::BLOCKERS_PENDING_ORDER], $dissolution->preview->blockers);
        self::assertSame(OrganisationDissolutionPool::STOP_PROVIDER_IMMEDIATE, $dissolution->preview->pools[0]->stop);
        self::assertSame(3, $dissolution->preview->membersActive);
    }

    public function testADissolutionUnderWayIsReadAsItsProgress(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetMeOrganisationDissolution, ['state' => 'dissolving']);

        $dissolution = $fake->forMember()->api()->me()->organisations()->dissolution('org');

        self::assertNull($dissolution->preview);
        self::assertSame(OrganisationDissolutionProgress::STATE_DISSOLVING, $dissolution->progress?->state);
    }
}

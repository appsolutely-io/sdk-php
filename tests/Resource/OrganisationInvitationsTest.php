<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OrganisationInvitationsTest extends TestCase
{
    public function testInvitationActionsForwardTokensAndKeepAdmissionAndEmptyAnswers(): void
    {
        $fake = new FakeClient();
        foreach (['createMeOrganisationInvitation', 'resendMeOrganisationInvitation'] as $id) {
            $fake->answer(Operation::from($id), ['id' => 'invite', 'email' => 'member@example.test', 'expires_at' => '2026-11-01T00:00:00Z']);
        }
        $fake->answer(Operation::from('acceptMeOrganisationInvitation'), ['organisation' => 'org', 'membership' => 'membership', 'admission' => 'membership-only'])
            ->answer(Operation::from('declineMeOrganisationInvitation'))
            ->answer(Operation::from('revokeMeOrganisationInvitation'))
            ->answerPage(Operation::from('listMeOrganisationInvitations'), []);
        $invitations = $fake->forMember()->api()->me()->organisations()->invitations();
        self::assertSame('invite', $invitations->create('org', 'member@example.test', 'create')->value->id);
        self::assertSame('membership-only', $invitations->accept('org', 'invite', str_repeat('a', 64), 'accept')->value->admission);
        self::assertSame(['token' => str_repeat('a', 64)], $fake->lastCall(Operation::from('acceptMeOrganisationInvitation'))->body);
        self::assertSame(204, $invitations->decline('org', 'invite', str_repeat('b', 64), 'decline')->status);
        self::assertSame('invite', $invitations->resend('org', 'invite', 'resend')->value->id);
        self::assertSame(204, $invitations->revoke('org', 'invite', 'revoke')->status);
        self::assertSame([], iterator_to_array($invitations->list('org')));
        foreach ($fake->calls() as $call) {
            if ($call->operation !== Operation::from('listMeOrganisationInvitations')) {
                self::assertNotNull($call->idempotencyKey);
            }
        }
    }
}

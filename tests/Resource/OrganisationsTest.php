<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OrganisationsTest extends TestCase
{
    public function testOrganisationCreationAndDissolutionKeepKeysAndServerState(): void
    {
        $create = Operation::from('createMeOrganisation');
        $dissolve = Operation::from('dissolveMeOrganisation');
        $fake = (new FakeClient())
            ->answer($create, ['id' => 'org', 'name' => 'Studio', 'role' => 'owner', 'frozen' => false, 'joined_at' => '2026-10-01T12:00:00Z'], 201)
            ->answer($dissolve, ['id' => 'operation', 'status' => 'queued'], 202);
        $organisations = $fake->forMember()->api()->me()->organisations();
        self::assertSame('Studio', $organisations->create('Studio', 'create-key')->value->name);
        self::assertSame(['name' => 'Studio'], $fake->lastCall($create)->body);
        self::assertSame('queued', $organisations->dissolve('org', 'end-key')->value->status);
        self::assertSame('end-key', $fake->lastCall($dissolve)->idempotencyKey);
        self::assertCount(2, $fake->calls());
    }

    public function testListsFollowServerCursorsAndLeaveKeepsTheEmptyAnswer(): void
    {
        $list = Operation::from('listMeOrganisations');
        $changes = Operation::from('listMeOrganisationChanges');
        $leave = Operation::from('leaveMeOrganisation');
        $fake = (new FakeClient())->answerPage($list, [], 'next')->answerPage($list, [])->answerPage($changes, [])->answer($leave);
        $organisations = $fake->forMember()->api()->me()->organisations();
        self::assertSame([], iterator_to_array($organisations->list()));
        self::assertSame('next', $fake->lastCall($list)->query['cursor'] ?? null);
        self::assertSame([], iterator_to_array($organisations->changes('org')));
        self::assertSame(204, $organisations->leave('org', 'leave-key')->status);
        self::assertSame('leave-key', $fake->lastCall($leave)->idempotencyKey);
    }
}

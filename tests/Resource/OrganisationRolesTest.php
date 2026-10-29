<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OrganisationRolesTest extends TestCase
{
    public function testMemberRoleWritesKeepTheirVerbsKeysAndReplayMetadata(): void
    {
        $record = ['organisation' => 'org', 'membership' => 'membership', 'roles' => ['editor']];
        $fake = (new FakeClient())
            ->answer(Operation::from('getOrganisationRole'), $record)
            ->answer(Operation::from('assignOrganisationRole'), $record, headers: ['Idempotent-Replayed' => 'true'])
            ->answer(Operation::from('revokeOrganisationRole'), [...$record, 'roles' => []])
            ->answerPage(Operation::from('listOrganisationRoles'), [['key' => 'editor', 'retired' => false]]);
        $roles = $fake->forMember()->api()->me()->organisations()->roles();
        self::assertSame(['editor'], $roles->get('org', 'membership')->roles);
        $assigned = $roles->assign('org', 'membership', 'edi/tor', 'assign-key');
        self::assertTrue($assigned->replayed);
        self::assertSame('assign-key', $assigned->idempotencyKey);
        self::assertSame('edi/tor', $fake->lastCall(Operation::from('assignOrganisationRole'))->pathParameters['key'] ?? null);
        self::assertSame([], $roles->revoke('org', 'membership', 'editor', 'revoke-key')->value->roles);
        self::assertSame('revoke-key', $fake->lastCall(Operation::from('revokeOrganisationRole'))->idempotencyKey);
        self::assertSame('editor', $roles->list('org')->items[0]->key);
    }
}

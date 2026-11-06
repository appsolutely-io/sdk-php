<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class BackendOrganisationRolesTest extends TestCase
{
    public function testBackendRolesUseMachineCredentialsAndNoUndocumentedKey(): void
    {
        $fake = new FakeClient();
        foreach (['getBackendOrganisationRole', 'assignBackendOrganisationRole', 'revokeBackendOrganisationRole'] as $id) {
            $fake->answer(Operation::from($id), ['organisation' => 'org', 'membership' => 'membership', 'roles' => ['editor']]);
        }
        foreach (['createBackendOrganisationRole', 'retireBackendOrganisationRole'] as $id) {
            $fake->answer(Operation::from($id), ['organisation' => 'org', 'key' => 'editor', 'retired' => $id === 'retireBackendOrganisationRole']);
        }
        $fake->answerPage(Operation::from('listBackendOrganisationRoles'), [['key' => 'editor', 'retired' => false]]);
        $roles = $fake->backend()->organisationRoles();
        self::assertSame(['editor'], $roles->get('org', 'membership')->roles);
        $roles->assign('org', 'membership', 'editor');
        $roles->revoke('org', 'membership', 'editor');
        self::assertFalse($roles->create('org', 'editor')->retired);
        self::assertTrue($roles->retire('org', 'editor')->retired);
        self::assertSame('editor', $roles->list('org')->items[0]->key);
        foreach ($fake->calls() as $call) {
            self::assertSame(FakeClient::BACKEND_TOKEN, $call->token);
            self::assertNull($call->idempotencyKey);
        }
        self::assertCount(6, $fake->calls());
    }
}

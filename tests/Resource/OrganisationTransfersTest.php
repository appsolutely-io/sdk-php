<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OrganisationTransfersTest extends TestCase
{
    public function testTransferRequestsForwardTheReviewedRevisionWithoutOrchestration(): void
    {
        $fake = new FakeClient();
        foreach (['createMeOrganisationTransfer', 'getMeOrganisationTransfer'] as $id) {
            $fake->answer(Operation::from($id), ['id' => 'transfer', 'organisation' => 'org', 'status' => 'pending']);
        }
        foreach (['acceptMeOrganisationTransfer', 'createMeOrganisationBinding', 'reconcileMeOrganisationBinding', 'cancelMeOrganisationTransfer'] as $id) {
            $fake->answer(Operation::from($id), ['id' => 'operation', 'status' => 'queued']);
        }
        $fake->answer(Operation::from('reviewMeOrganisationTransfer'), ['revision' => 'review:3', 'pools' => [['id' => 'pool', 'plan' => 'team', 'payer_outcome' => 'recipient_on_acceptance', 'requires_binding' => true, 'quantity' => 2, 'currency' => 'USD', 'revision' => 'pool:2', 'total' => null]]]);
        $transfers = $fake->forMember()->api()->me()->organisations()->transfers();
        self::assertSame('transfer', $transfers->create('org', 'recipient@example.test')->value->id);
        self::assertSame('pending', $transfers->get('org', 'transfer')->status);
        self::assertNull($transfers->review('org', 'transfer')->pools[0]->total);
        self::assertSame('queued', $transfers->accept('org', 'transfer', 'review:3', 'accept-key')->value->status);
        self::assertSame(['revision' => 'review:3'], $fake->lastCall(Operation::from('acceptMeOrganisationTransfer'))->body);
        $transfers->bind('org', 'transfer', 'pool', 'pool:2', 'https://app.example.test/success', 'https://app.example.test/cancel');
        $transfers->reconcile('org', 'transfer', 'binding', 'pool:2', 'session', 'result');
        self::assertSame(['revision' => 'pool:2', 'session_id' => 'session', 'session_result' => 'result'], $fake->lastCall(Operation::from('reconcileMeOrganisationBinding'))->body);
        self::assertSame('queued', $transfers->cancel('org', 'transfer')->value->status);
        self::assertCount(7, $fake->calls());
    }
}

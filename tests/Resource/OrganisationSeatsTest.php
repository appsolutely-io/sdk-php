<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OrganisationSeatsTest extends TestCase
{
    public function testPoolsAndSeatsKeepUnavailableMembersAndKeyedDeletion(): void
    {
        $seat = ['id' => 'seat', 'pool' => 'pool', 'granted_at' => '2026-10-01T12:00:00Z', 'passing' => false];
        $fake = (new FakeClient())
            ->answerPage(Operation::from('listMeOrganisationPools'), [['id' => 'pool', 'quantity' => 3, 'scheduled' => 2, 'ceiling' => 3, 'occupied' => 1]])
            ->answerPage(Operation::from('listMeOrganisationPoolSeats'), [$seat])
            ->answer(Operation::from('createMeOrganisationSeat'), $seat)
            ->answer(Operation::from('deleteMeOrganisationSeat'), headers: ['Idempotent-Replayed' => 'true']);
        $seats = $fake->forMember()->api()->me()->organisations()->seats();
        self::assertSame(1, iterator_to_array($seats->pools('org', 2))[0]->occupied);
        self::assertNull(iterator_to_array($seats->list('org', 'pool'))[0]->member);
        self::assertSame('seat', $seats->create('org', 'pool', 'member', idempotencyKey: 'grant')->value->id);
        $deleted = $seats->delete('org', 'seat', 'take');
        self::assertSame(204, $deleted->status);
        self::assertNull($deleted->data);
        self::assertTrue($deleted->replayed);
        self::assertSame('take', $deleted->idempotencyKey);
        self::assertNull($fake->lastCall(Operation::from('deleteMeOrganisationSeat'))->body);
        self::assertCount(4, $fake->calls());
    }
}

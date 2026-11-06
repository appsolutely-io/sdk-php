<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class OrganisationBillingTest extends TestCase
{
    public function testBillingAndReplacementCallsReturnQueuedOutcomesWithoutFollowingThem(): void
    {
        $fake = new FakeClient();
        foreach (['getMeOrganisationBillingOperation', 'createMeOrganisationRecoveryBinding', 'cancelMeOrganisationRecoveryBinding', 'reconcileMeOrganisationRecoveryBinding', 'createMeOrganisationReplacement', 'closeMeOrganisationReplacement', 'completeMeOrganisationReplacement', 'reconcileMeOrganisationReplacement', 'takeoverMeOrganisationBilling'] as $id) {
            $fake->answer(Operation::from($id), ['id' => 'operation', 'status' => 'queued', 'confirmation_url' => 'https://site.test/confirm']);
        }
        foreach (['currentMeOrganisationReplacement', 'getMeOrganisationReplacement'] as $id) {
            $fake->answer(Operation::from($id), ['id' => 'setup', 'status' => 'open', 'first_charge_at' => '2026-11-01T00:00:00Z', 'expires_at' => '2026-10-31T00:00:00Z']);
        }
        $fake->answer(Operation::from('reviewMeOrganisationReplacement'), ['pool' => 'pool', 'plan' => 'team', 'quantity' => 2, 'currency' => 'USD', 'unit_amount' => 100, 'tax' => null, 'total' => 200, 'total_shown' => 'before_provider_tax', 'first_charge_at' => '2026-11-01T00:00:00Z', 'revision' => 'review:4'])
            ->answer(Operation::from('reviewMeOrganisationBilling'), ['revision' => 'review:4', 'pools' => []]);
        $organisations = $fake->forMember()->api()->me()->organisations();
        $billing = $organisations->billing();
        self::assertSame('queued', $billing->operation('org', 'operation')->status);
        self::assertSame('review:4', $billing->review('org')->revision);
        self::assertSame('queued', $billing->takeover('org', 'review:4')->value->status);
        $bindings = $organisations->recoveryBindings();
        self::assertSame('https://site.test/confirm', $bindings->create('org', 'pool', 'review:4', 'https://app.example.test/success', 'https://app.example.test/cancel')->value->confirmationUrl);
        $bindings->cancel('org', 'binding');
        $bindings->reconcile('org', 'binding', 'review:4');
        self::assertSame(['revision' => 'review:4'], $fake->lastCall(Operation::from('reconcileMeOrganisationRecoveryBinding'))->body);
        $replacements = $organisations->replacements();
        $replacements->create('org', 'pool', 'review:4', 'https://app.example.test/success', 'https://app.example.test/cancel');
        self::assertSame('setup', $replacements->current('org', 'pool')->id);
        self::assertNull($replacements->review('org', 'pool')->tax);
        self::assertSame('open', $replacements->get('org', 'setup')->status);
        $replacements->close('org', 'setup');
        $replacements->complete('org', 'setup');
        $replacements->reconcile('org', 'setup');
        self::assertCount(13, $fake->calls());
    }
}

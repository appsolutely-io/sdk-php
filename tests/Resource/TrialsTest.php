<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Exception\ConflictException;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class TrialsTest extends TestCase
{
    public function testATrialIsOneUnkeyedRequestWithTheServersDates(): void
    {
        $operation = Operation::from('createMeTrial');
        $fake = (new FakeClient())->answer($operation, ['state' => 'active', 'kind' => 'trial', 'starts_at' => '2026-10-01T12:00:00Z', 'expires_at' => null, 'payment_method_required' => false]);
        $trial = $fake->forMember()->api()->me()->trials()->create();
        self::assertSame('active', $trial->state);
        self::assertNull($trial->expiresAt);
        self::assertFalse($trial->paymentMethodRequired);
        self::assertNull($fake->lastCall($operation)->idempotencyKey);
        self::assertCount(1, $fake->calls());
    }

    public function testACheckoutRefusalDoesNotStartACheckout(): void
    {
        $fake = (new FakeClient())->refuse(Operation::from('createMeTrial'), 'checkout-required', 409, 'Checkout required');
        try {
            $fake->forMember()->api()->me()->trials()->create();
            self::fail('The refusal was lost.');
        } catch (ConflictException $problem) {
            self::assertTrue($problem->hasType('checkout-required'));
        }
        self::assertCount(1, $fake->calls());
    }
}

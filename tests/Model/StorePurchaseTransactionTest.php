<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Model;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\StorePurchaseTransaction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorePurchaseTransactionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, ?string, string}>
     */
    public static function ownership(): iterable
    {
        yield 'credited subscription' => ['recorded', true, 'subscription-ref', 'subscription'];
        yield 'credited one-time purchase' => ['recorded', true, null, 'one_time'];
        yield 'unowned ended purchase' => ['recorded', false, null, 'subscription'];
        yield 'waiting for attribution' => ['unclaimed', false, null, 'subscription'];
    }

    #[DataProvider('ownership')]
    public function testOwnershipIsReadIndependentlyOfTheOutcomeAndSubscription(string $outcome, bool $belongsToYou, ?string $subscriptionId, string $kind): void
    {
        $purchase = StorePurchaseTransaction::from(Fields::of([
            'outcome' => $outcome,
            'belongs_to_you' => $belongsToYou,
            'subscription_id' => $subscriptionId,
            'status' => $kind === 'subscription' ? ($belongsToYou ? 'active' : 'canceled') : null,
            'kind' => $kind,
            'environment' => 'production',
            'receipt_hint' => 'kept',
        ], StorePurchaseTransaction::SCHEMA));

        self::assertSame($belongsToYou, $purchase->belongsToYou);
        self::assertSame($outcome, $purchase->outcome);
        self::assertSame($subscriptionId, $purchase->subscriptionId);
        self::assertSame(['receipt_hint' => 'kept'], $purchase->extra);
    }

    public function testAMissingOwnershipFlagIsRefused(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('belongs_to_you');

        StorePurchaseTransaction::from(Fields::of(['outcome' => 'recorded'], StorePurchaseTransaction::SCHEMA));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notBoolean(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['false'];
        yield 'integer' => [0];
    }

    #[DataProvider('notBoolean')]
    public function testOwnershipMustBeABoolean(mixed $value): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('belongs_to_you');

        StorePurchaseTransaction::from(Fields::of(['outcome' => 'recorded', 'belongs_to_you' => $value], StorePurchaseTransaction::SCHEMA));
    }
}

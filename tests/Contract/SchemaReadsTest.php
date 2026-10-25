<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaReadsTest extends TestCase
{
    use SchemaReads;

    /**
     * @return array<string, array<string, array{document: array<array-key, mixed>, site: mixed}>>
     */
    protected static function asTheSiteSends(): array
    {
        return [
            'Order' => ['tax_amount' => ['document' => ['type' => 'integer'], 'site' => null]],
            'Seat' => ['member' => ['document' => ['type' => 'string'], 'site' => null]],
            'AccountSubscription' => SiteApiOverrides::AS_THE_SITE_SENDS['AccountSubscription'],
        ];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unrelatedFields(): iterable
    {
        yield 'same schema integer' => ['Order', 'amount', 'nullableInt'];
        yield 'same schema string' => ['Order', 'reference', 'nullableString'];
        yield 'other schema integer' => ['SeatPool', 'quantity', 'nullableInt'];
        yield 'other schema string' => ['OrderLineSummary', 'currency', 'nullableString'];
    }

    #[DataProvider('unrelatedFields')]
    public function testAnOverrideDoesNotExemptAnUnrelatedField(string $schema, string $field, string $accessor): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('reads "' . $field . '"');

        self::assertReadTypesMatch($schema, [$field => [$accessor]], self::class);
    }

    public function testAnOverrideAppliesInsideAReferencedListItem(): void
    {
        self::assertReadTypesMatch('SeatPool', ['seats[].member' => ['nullableString']], self::class);
    }

    public function testAnOverrideAppliesThroughComposition(): void
    {
        self::assertReadTypesMatch('OrderPaidEventData', ['tax_amount' => ['nullableInt']], self::class);
    }

    public function testAComposedObjectOverrideStillAllowsItsNestedReads(): void
    {
        self::assertReadsMatch('SubscriptionStartedEventData', [...self::requiredPaths(Document::schema('SubscriptionStartedEventData'), '', []), 'pending_period.start'], self::class);
    }

    public function testAnObjectOverrideStillAllowsItsNestedReads(): void
    {
        self::assertReadsMatch('AccountSubscription', [...self::requiredPaths(Document::schema('AccountSubscription'), '', []), 'pending_period.start', 'pending_period.status'], self::class);
        self::assertReadTypesMatch('AccountSubscription', ['pending_period.start' => ['time'], 'pending_period.status' => ['string']], self::class);
    }
}

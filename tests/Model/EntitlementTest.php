<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Model;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Model\Entitlement;
use Appsolutely\Sdk\Model\Fields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EntitlementTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function kinds(): iterable
    {
        yield 'trial' => ['trial'];
        yield 'paid' => ['paid'];
        yield 'future' => ['future'];
    }

    #[DataProvider('kinds')]
    public function testItReadsTheKindWithoutInferringIt(string $kind): void
    {
        $entry = Entitlement::from(Fields::of(['key' => 'pro', 'label' => 'Pro', 'quantity' => 1, 'kind' => $kind, 'future' => 2], Entitlement::SCHEMA));

        self::assertSame($kind, $entry->kind);
        self::assertSame(['future' => 2], $entry->extra);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidKinds(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['kind' => null]];
        yield 'integer' => [['kind' => 1]];
        yield 'boolean' => [['kind' => true]];
    }

    /** @param array<string, mixed> $kind */
    #[DataProvider('invalidKinds')]
    public function testTheKindIsRequired(array $kind): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('kind');

        Entitlement::from(Fields::of(['key' => 'pro', 'label' => 'Pro', 'quantity' => 1, ...$kind], Entitlement::SCHEMA));
    }
}

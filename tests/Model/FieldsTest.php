<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Model;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Model\Fields;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every model reads the site's JSON through Fields: one spelling of each
 * kind of value, a refusal that names the schema and the field when the
 * site breaks it, and a record of the fields read for the contract test.
 */
final class FieldsTest extends TestCase
{
    public function testItReadsEachKindOfValue(): void
    {
        $fields = Fields::of([
            'id' => 'a-1',
            'count' => 3,
            'flag' => false,
            'tags' => ['x', 'y'],
            'meta' => ['k' => 'v'],
            'child' => ['id' => 'c-1'],
            'children' => [['id' => 'c-2'], ['id' => 'c-3']],
        ], 'Thing');

        self::assertSame('a-1', $fields->string('id'));
        self::assertSame(3, $fields->int('count'));
        self::assertFalse($fields->bool('flag'));
        self::assertSame(['x', 'y'], $fields->strings('tags'));
        self::assertSame(['k' => 'v'], $fields->map('meta'));
        self::assertSame('c-1', $fields->object('child')->string('id'));
        self::assertSame(['c-2', 'c-3'], array_map(static fn(Fields $child): string => $child->string('id'), $fields->objects('children')));
    }

    public function testAnAbsentOrNullOptionalFieldIsNull(): void
    {
        $fields = Fields::of(['present' => null], 'Thing');

        self::assertNull($fields->optionalString('present'));
        self::assertNull($fields->optionalString('absent'));
        self::assertNull($fields->optionalInt('absent'));
        self::assertNull($fields->optionalBool('absent'));
        self::assertNull($fields->optionalTime('absent'));
        self::assertNull($fields->optionalMap('absent'));
        self::assertNull($fields->optionalObject('absent'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function times(): iterable
    {
        yield 'UTC to the second' => ['2026-10-08T12:34:56Z', '2026-10-08T12:34:56+00:00'];
        yield 'an offset, kept as the same instant in UTC' => ['2026-10-08T14:34:56+02:00', '2026-10-08T12:34:56+00:00'];
        yield 'fractional seconds' => ['2026-10-08T12:34:56.789Z', '2026-10-08T12:34:56+00:00'];
        yield 'a lower-case separator' => ['2026-10-08t12:34:56z', '2026-10-08T12:34:56+00:00'];
    }

    #[DataProvider('times')]
    public function testATimeIsReadAsAnInstantInUtc(string $wire, string $expected): void
    {
        $time = Fields::of(['at' => $wire], 'Thing')->time('at');

        self::assertInstanceOf(DateTimeImmutable::class, $time);
        self::assertSame($expected, $time->format(DATE_ATOM));
        self::assertSame('UTC', $time->getTimezone()->getName());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, \Closure(Fields): mixed}>
     */
    public static function brokenFields(): iterable
    {
        yield 'a required field absent' => [[], static fn(Fields $fields): mixed => $fields->string('id')];
        yield 'a required field null' => [['id' => null], static fn(Fields $fields): mixed => $fields->string('id')];
        yield 'a number for a string' => [['id' => 7], static fn(Fields $fields): mixed => $fields->string('id')];
        yield 'a numeric string for an integer' => [['id' => '7'], static fn(Fields $fields): mixed => $fields->int('id')];
        yield 'a float for an integer' => [['id' => 7.5], static fn(Fields $fields): mixed => $fields->int('id')];
        yield 'a string for a boolean' => [['id' => 'true'], static fn(Fields $fields): mixed => $fields->bool('id')];
        yield 'a date without a time' => [['id' => '2026-10-08'], static fn(Fields $fields): mixed => $fields->time('id')];
        yield 'a time without an offset' => [['id' => '2026-10-08T12:34:56'], static fn(Fields $fields): mixed => $fields->time('id')];
        yield 'an impossible date' => [['id' => '2026-02-30T12:34:56Z'], static fn(Fields $fields): mixed => $fields->time('id')];
        yield 'a list for an object' => [['id' => [1, 2]], static fn(Fields $fields): mixed => $fields->object('id')];
        yield 'a list holding a non-string' => [['id' => ['a', 1]], static fn(Fields $fields): mixed => $fields->strings('id')];
        yield 'a list holding a non-object' => [['id' => ['a']], static fn(Fields $fields): mixed => $fields->objects('id')];
        yield 'an object for a list' => [['id' => ['k' => 'v']], static fn(Fields $fields): mixed => $fields->strings('id')];
        yield 'an optional field of the wrong type' => [['id' => 7], static fn(Fields $fields): mixed => $fields->optionalString('id')];
    }

    /**
     * @param array<string, mixed> $object
     * @param \Closure(Fields): mixed $read
     */
    #[DataProvider('brokenFields')]
    public function testAFieldTheSiteBrokeIsRefusedNamingTheSchemaAndTheField(array $object, \Closure $read): void
    {
        try {
            $read(Fields::of($object, 'Thing'));
            self::fail('The field was accepted.');
        } catch (UnexpectedResponseException $exception) {
            self::assertStringContainsString('Thing', $exception->getMessage());
            self::assertStringContainsString('"id"', $exception->getMessage());
        }
    }

    public function testANestedRefusalNamesThePathToTheField(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"items[1].price"');

        $fields = Fields::of(['items' => [['price' => 1], ['price' => 'x']]], 'Order');
        foreach ($fields->objects('items') as $item) {
            $item->int('price');
        }
    }

    public function testEveryMemberStaysReadableWhateverWasRead(): void
    {
        $object = ['id' => 'a-1', 'added_later' => ['x' => 1]];
        $fields = Fields::of($object, 'Thing');
        $fields->string('id');

        self::assertSame($object, $fields->all());
    }

    public function testTheFieldsReadAreRecordedWithTheirPath(): void
    {
        $fields = Fields::of(['id' => 'a', 'child' => ['id' => 'c'], 'children' => [['id' => 'd']], 'missing' => null], 'Thing');
        $fields->string('id');
        $fields->object('child')->string('id');
        $fields->objects('children');
        $fields->optionalString('missing');
        $fields->optionalObject('none');

        self::assertSame(['id', 'child', 'child.id', 'children', 'missing', 'none'], $fields->reads());
    }
}

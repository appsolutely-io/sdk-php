<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Webhooks\Data;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use Appsolutely\Sdk\Exception\UnexpectedPayloadException;
use Appsolutely\Sdk\Webhooks\Data\Fields;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FieldsTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private static function fields(array $data): Fields
    {
        return Fields::of($data, 'order.paid');
    }

    public function testATimeIsReadAsRfc3339InUtc(): void
    {
        $fields = self::fields([
            'zulu' => '2026-10-08T12:34:56Z',
            'offset' => '2026-10-08T14:34:56+02:00',
            'fraction' => '2026-10-08T12:34:56.123456789Z',
            'lower' => '2026-10-08t12:34:56z',
        ]);

        self::assertSame('2026-10-08T12:34:56.000000+00:00', $fields->time('zulu')->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-10-08T12:34:56.000000+00:00', $fields->time('offset')->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-10-08T12:34:56.123456+00:00', $fields->time('fraction')->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-10-08T12:34:56.000000+00:00', $fields->time('lower')->format('Y-m-d\TH:i:s.uP'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notRfc3339(): iterable
    {
        yield 'no offset' => ['2026-10-08T12:34:56'];
        yield 'date only' => ['2026-10-08'];
        yield 'space separator' => ['2026-10-08 12:34:56Z'];
        yield 'month 13' => ['2026-13-08T12:34:56Z'];
        yield 'day 31 of a 30-day month' => ['2026-09-31T12:34:56Z'];
        yield 'hour 24' => ['2026-10-08T24:00:00Z'];
        yield 'unix seconds' => ['1791462896'];
    }

    #[DataProvider('notRfc3339')]
    public function testATimeThatIsNotRfc3339IsRefused(string $value): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('data.paid_at is not an RFC 3339 date-time');

        self::fields(['paid_at' => $value])->time('paid_at');
    }

    public function testANullableFieldIsNullWhenAbsentOrNull(): void
    {
        $fields = self::fields(['note' => null]);

        self::assertNull($fields->nullableString('note'));
        self::assertNull($fields->nullableString('summary'));
        self::assertNull($fields->nullableInt('status'));
        self::assertNull($fields->nullableTime('expired_at'));
        self::assertNull($fields->nullableObject('payment'));
        self::assertNull($fields->nullableMap('data'));
        self::assertNull($fields->nullableObjects('items'));
    }

    public function testAMissingRequiredFieldNamesTheTypeAndThePath(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('The order.paid delivery\'s data.items[1].quantity is missing.');

        $items = self::fields(['items' => [['quantity' => 1], ['price' => 100]]])->objects('items');
        $items[1]->int('quantity');
    }

    /**
     * @return iterable<string, array{mixed, Closure(Fields): mixed, string}>
     */
    public static function wrongTypes(): iterable
    {
        $string = static fn(Fields $fields): string => $fields->string('value');
        $int = static fn(Fields $fields): int => $fields->int('value');

        yield 'a number as a string' => [12, $string, 'is not a string'];
        yield 'a numeric string as an integer' => ['12', $int, 'is not an integer'];
        yield 'a float as an integer' => [12.0, $int, 'is not an integer'];
        yield 'an integer as a boolean' => [1, static fn(Fields $fields): bool => $fields->bool('value'), 'is not a boolean'];
        yield 'a list as an object' => [[1, 2], static fn(Fields $fields): Fields => $fields->object('value'), 'is not an object'];
        yield 'an object as a list' => [['a' => 1], static fn(Fields $fields): array => $fields->objects('value'), 'is not a list'];
        yield 'a null required string' => [null, $string, 'is missing'];
    }

    /**
     * @param Closure(Fields): mixed $read
     */
    #[DataProvider('wrongTypes')]
    public function testAFieldOfTheWrongTypeIsRefused(mixed $value, Closure $read, string $message): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('data.value ' . $message);

        $read(self::fields(['value' => $value]));
    }

    public function testAnEmptyObjectDecodedAsAnEmptyArrayIsAnEmptyMap(): void
    {
        self::assertSame([], self::fields(['totals' => []])->intMap('totals'));
        self::assertSame(['seats' => 3, '7' => 1], self::fields(['totals' => ['seats' => 3, 7 => 1]])->intMap('totals'));
    }

    public function testAMapOfIntegersRefusesAnotherValue(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('data.totals.seats is not an integer');

        self::fields(['totals' => ['seats' => '3']])->intMap('totals');
    }

    public function testTheFieldsNotReadAreKeptAsExtra(): void
    {
        $fields = self::fields(['id' => 'o_1', 'note' => null, 'gift_wrap' => true, 'tags' => ['a']]);
        $fields->string('id');
        $fields->nullableString('note');

        self::assertSame(['gift_wrap' => true, 'tags' => ['a']], $fields->extra());
    }

    public function testExceptLeavesOutTheFieldsAnotherReaderOwns(): void
    {
        $fields = self::fields(['id' => 'o_1', 'payment' => ['reference' => 'p_1'], 'subject' => 's_1']);

        $order = $fields->except('payment', 'subject');
        $order->string('id');

        self::assertSame([], $order->extra());
        self::assertSame('p_1', $fields->object('payment')->string('reference'));
    }

    public function testAKeyFromTheWireReachesTheMessageOnlyAsPrintableText(): void
    {
        try {
            self::fields(['totals' => ["seats\nforged log line" => 'x']])->intMap('totals');
            self::fail('A map with a text value was accepted.');
        } catch (UnexpectedPayloadException $exception) {
            self::assertStringNotContainsString("\n", $exception->getMessage());
            self::assertInstanceOf(AppsolutelyException::class, $exception);
        }
    }
}

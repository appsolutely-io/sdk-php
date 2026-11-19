<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Admin;

use Appsolutely\Sdk\Admin\Model\FormEntry;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Model\Fields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormEntryDataTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, mixed>|null}> */
    public static function submittedData(): iterable
    {
        yield 'object' => [['answer' => 'yes']];
        yield 'list' => [['first', 'second']];
        yield 'empty object or list' => [[]];
        yield 'null' => [null];
    }

    /** @param array<array-key, mixed>|null $data */
    #[DataProvider('submittedData')]
    public function testSubmittedDataKeepsTheDecodedShape(?array $data): void
    {
        $entry = FormEntry::from(Fields::of(['id' => 'entry-one', 'data' => $data], FormEntry::SCHEMA));
        self::assertSame($data, $entry->data);
    }

    public function testAScalarIsNotSubmittedFormData(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        FormEntry::from(Fields::of(['id' => 'entry-one', 'data' => 'invalid-shape'], FormEntry::SCHEMA));
    }
}

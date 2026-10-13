<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public function at(string $level): array
    {
        return array_values(array_filter($this->records, static fn(array $record): bool => $record['level'] === $level));
    }

    /**
     * Every message and context, as one string to search.
     */
    public function text(): string
    {
        return implode("\n", array_map(static fn(array $record): string => $record['message'] . ' ' . json_encode($record['context'], JSON_THROW_ON_ERROR), $this->records));
    }
}

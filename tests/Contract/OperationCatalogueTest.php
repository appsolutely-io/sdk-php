<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Api\Audience;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Operation names every operation of the pinned document with what the
 * client relies on: its method and path, who may call it, the status of its
 * success, whether it takes an Idempotency-Key and whether it is a cursor
 * list.
 */
final class OperationCatalogueTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function documentedOperations(): iterable
    {
        foreach (array_keys(Document::operations()) as $id) {
            yield $id => [$id];
        }
    }

    #[DataProvider('documentedOperations')]
    public function testEveryDocumentedOperationIsACaseThatMatchesIt(string $id): void
    {
        $documented = Document::operations()[$id];
        $operation = Operation::tryFrom($id);

        self::assertNotNull($operation, sprintf('The document\'s %s %s (%s) has no Operation case.', $documented['method'], $documented['path'], $id));
        self::assertSame($documented['method'], $operation->method(), $id);
        self::assertSame($documented['path'], $operation->template(), $id);
        self::assertSame(self::successStatus($documented['operation']), $operation->successStatus(), $id);
        self::assertSame(self::takesIdempotencyKey($documented['parameters']), $operation->takesIdempotencyKey(), $id);
        self::assertSame(self::isCursorList($documented), $operation->isCursorList(), $id);
        self::assertSame(self::audience($documented), $operation->audience(), $id);
    }

    public function testEveryCaseIsADocumentedOperation(): void
    {
        $documented = array_keys(Document::operations());
        foreach (Operation::cases() as $operation) {
            self::assertContains($operation->value, $documented, sprintf('Operation::%s names no operation of the pinned document.', $operation->name));
        }
        self::assertCount(count($documented), Operation::cases());
    }

    public function testAPathFillsEachPlaceholderEncoded(): void
    {
        self::assertSame(
            '/api/v1/webhook-subscriptions/sub%2F1/deliveries/msg%201/redeliver',
            Operation::RedeliverWebhookDelivery->path(['subscription' => 'sub/1', 'event' => 'msg 1']),
        );
        self::assertSame('/api/v1/articles', Operation::ListArticles->path());
    }

    /**
     * @return iterable<string, array{Operation, array<string, string>}>
     */
    public static function unfillablePaths(): iterable
    {
        yield 'a missing identifier' => [Operation::GetArticle, []];
        yield 'an empty identifier' => [Operation::GetArticle, ['id' => '']];
        yield 'an identifier the path has no place for' => [Operation::GetArticle, ['id' => 'a-1', 'slug' => 'x']];
    }

    /**
     * @param array<string, string> $parameters
     */
    #[DataProvider('unfillablePaths')]
    public function testAPathThatCannotBeFilledIsRefused(Operation $operation, array $parameters): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        $operation->path($parameters);
    }

    /**
     * @param array<string, mixed> $operation
     */
    private static function successStatus(array $operation): int
    {
        $codes = array_values(array_filter(array_keys(Document::map($operation['responses'] ?? null)), static fn(string $code): bool => $code[0] === '2'));
        self::assertCount(1, $codes);

        return (int) $codes[0];
    }

    /**
     * @param list<array<string, mixed>> $parameters
     */
    private static function takesIdempotencyKey(array $parameters): bool
    {
        foreach ($parameters as $parameter) {
            $name = $parameter['name'] ?? null;
            if (($parameter['in'] ?? null) === 'header' && is_string($name) && strcasecmp($name, 'Idempotency-Key') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * A list answered as `{data, next_cursor}` that takes a cursor.
     *
     * @param array{method: string, path: string, operation: array<string, mixed>, parameters: list<array<string, mixed>>} $documented
     */
    private static function isCursorList(array $documented): bool
    {
        $takesCursor = false;
        foreach ($documented['parameters'] as $parameter) {
            $takesCursor = $takesCursor || (($parameter['in'] ?? null) === 'query' && ($parameter['name'] ?? null) === 'cursor');
        }

        $responses = Document::map($documented['operation']['responses'] ?? null);
        $success = [];
        foreach ($responses as $code => $response) {
            if ((int) $code === self::successStatus($documented['operation'])) {
                $success = Document::map($response);
            }
        }
        $schema = Document::resolve(Document::map(Document::map(Document::map($success['content'] ?? null)['application/json'] ?? null)['schema'] ?? null));

        return $takesCursor && isset(Document::map($schema['properties'] ?? null)['data']);
    }

    /**
     * Anyone when the operation names no security requirement; otherwise
     * the member for the member's own routes (`/api/v1/me`, and the sync
     * resources named `me-*`) and the administrator for the rest, as the
     * site mounts them in two audience groups.
     *
     * @param array{method: string, path: string, operation: array<string, mixed>, parameters: list<array<string, mixed>>} $documented
     */
    private static function audience(array $documented): Audience
    {
        $security = $documented['operation']['security'] ?? Document::get()['security'] ?? [];
        if ($security === []) {
            return Audience::Anyone;
        }

        return preg_match('#^/api/v1/(me(/|$)|sync/me-)#', $documented['path']) === 1 ? Audience::Member : Audience::Administrator;
    }
}

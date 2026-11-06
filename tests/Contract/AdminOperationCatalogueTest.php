<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Admin\AdminClient;
use Appsolutely\Sdk\Admin\AdminOperation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every Administrator API operation this client types is one the pinned
 * Administrator API document serves, as the document describes it.
 */
final class AdminOperationCatalogueTest extends TestCase
{
    /**
     * @return iterable<string, array{AdminOperation}>
     */
    public static function operations(): iterable
    {
        foreach (AdminOperation::cases() as $operation) {
            yield $operation->value => [$operation];
        }
    }

    #[DataProvider('operations')]
    public function testTheOperationIsTheDocumentsAtItsPathAndMethod(AdminOperation $operation): void
    {
        $described = self::described($operation);

        self::assertSame($operation->value, $described['operationId'] ?? null);
        self::assertFalse((bool) ($described['x-panel-only'] ?? false), sprintf('%s is served to the panel alone.', $operation->value));
    }

    #[DataProvider('operations')]
    public function testItAsksForTheScopeTheDocumentNames(AdminOperation $operation): void
    {
        $scopes = [];
        foreach (Document::list(self::described($operation)['security'] ?? []) as $requirement) {
            foreach (Document::list(Document::map($requirement)['administratorCredential'] ?? []) as $scope) {
                $scopes[] = $scope;
            }
        }

        self::assertSame([$operation->scope()], $scopes);
    }

    #[DataProvider('operations')]
    public function testItAnswersTheSuccessTheDocumentNames(AdminOperation $operation): void
    {
        $successes = array_values(array_filter(
            array_map('strval', array_keys(Document::map(self::described($operation)['responses'] ?? null))),
            static fn(string $status): bool => str_starts_with($status, '2'),
        ));

        self::assertSame([(string) $operation->successStatus()], $successes);
    }

    #[DataProvider('operations')]
    public function testItSendsTheKeyAndThePreconditionTheDocumentAsksFor(AdminOperation $operation): void
    {
        $headers = [];
        foreach (Document::list(self::described($operation)['parameters'] ?? []) as $parameter) {
            $parameter = AdminDocument::resolve(Document::map($parameter));
            if (($parameter['in'] ?? null) === 'header') {
                $name = $parameter['name'] ?? null;
                self::assertIsString($name);
                $headers[$name] = (bool) ($parameter['required'] ?? false);
            }
        }

        self::assertSame($operation->takesIdempotencyKey(), array_key_exists('Idempotency-Key', $headers));
        self::assertSame($operation->requiresIfMatch(), $headers['If-Match'] ?? false);
    }

    #[DataProvider('operations')]
    public function testAListPagesByCursor(AdminOperation $operation): void
    {
        $names = [];
        foreach (Document::list(self::described($operation)['parameters'] ?? []) as $parameter) {
            $names[] = AdminDocument::resolve(Document::map($parameter))['name'] ?? null;
        }

        self::assertSame($operation->isList(), in_array('cursor', $names, true) && in_array('limit', $names, true));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function described(AdminOperation $operation): array
    {
        $path = '/' . substr($operation->template(), strlen(AdminClient::BASE_PATH));
        $item = Document::map(Document::map(AdminDocument::get()['paths'] ?? null)[$path] ?? null);

        self::assertNotSame([], $item, sprintf('The document serves no %s.', $path));

        $described = Document::map($item[strtolower($operation->method())] ?? null);
        self::assertNotSame([], $described, sprintf('The document serves no %s %s.', $operation->method(), $path));

        return $described;
    }
}

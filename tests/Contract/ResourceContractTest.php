<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Api\AdministratorApi;
use Appsolutely\Sdk\Api\Audience;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\SyncPull;
use Appsolutely\Sdk\Model\SyncPush;
use Appsolutely\Sdk\Model\SyncResult;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Holds the typed client to the pinned Site API document: every operation
 * has exactly one method, every method names an operation and path the
 * document has and sits on the client of the operation's audience, and
 * every model reads only fields its schema defines and every field the
 * schema requires.
 */
final class ResourceContractTest extends TestCase
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
    public function testEveryDocumentedOperationHasExactlyOneMethod(string $id): void
    {
        $documented = Document::operations()[$id];
        $methods = [];
        foreach (self::endpoints() as $endpoint) {
            if ($endpoint['operation']->value === $id) {
                $methods[] = $endpoint['method'];
            }
        }

        self::assertNotSame([], $methods, sprintf('%s (%s %s) has no SDK method.', $id, $documented['method'], $documented['path']));
        self::assertCount(1, $methods, sprintf('%s has more than one SDK method: %s.', $id, implode(', ', $methods)));
    }

    public function testEveryMethodNamesAnOperationAndPathTheDocumentHas(): void
    {
        $operations = Document::operations();
        foreach (self::endpoints() as $endpoint) {
            $operation = $endpoint['operation'];
            self::assertArrayHasKey($operation->value, $operations, sprintf('%s calls %s, which the document does not have.', $endpoint['method'], $operation->value));
            self::assertSame(
                $operations[$operation->value]['method'] . ' ' . $operations[$operation->value]['path'],
                $operation->method() . ' ' . $operation->template(),
                sprintf('%s calls %s at a path or with a method the document does not give it.', $endpoint['method'], $operation->value),
            );
        }
    }

    public function testEveryMethodIsOnTheClientOfItsOperationsAudience(): void
    {
        foreach (self::endpoints() as $endpoint) {
            $expected = match ($endpoint['operation']->audience()) {
                Audience::Anyone, Audience::Administrator => [AdministratorApi::class],
                Audience::Member => [MemberApi::class],
            };

            self::assertSame($expected, $endpoint['roots'], sprintf('%s calls %s, an operation for %s, from %s.', $endpoint['method'], $endpoint['operation']->value, $endpoint['operation']->audience()->name, $endpoint['roots'] === [] ? 'no client' : implode(' and ', $endpoint['roots'])));
        }
    }

    public function testEveryKeyedWriteAcceptsTheCallersKey(): void
    {
        foreach (self::endpoints() as $endpoint) {
            if (!$endpoint['operation']->takesIdempotencyKey()) {
                continue;
            }

            $names = array_map(static fn(\ReflectionParameter $parameter): string => $parameter->getName(), (new ReflectionMethod(...explode('::', $endpoint['method'], 2)))->getParameters());
            self::assertContains('idempotencyKey', $names, sprintf('%s sends %s, which takes an Idempotency-Key, without accepting the caller\'s.', $endpoint['method'], $endpoint['operation']->value));
        }
    }

    /**
     * Fields the pinned document types otherwise than the site sends them,
     * where the model follows the site: sampled as the site sends them. Each
     * names what the document declares (its description aside), and the
     * override applies only while the document still declares exactly that,
     * so a document that changes, to agree with the site or otherwise, fails
     * until the entry is dropped or rewritten.
     *
     * The document declares an order's `status` an integer; the site sends
     * its order status as a string (`shipped`). It leaves an account's
     * `totals` an open object; the site sends a quantity per key.
     */
    private const array AS_THE_SITE_SENDS = [
        'Order' => [
            'status' => ['document' => ['oneOf' => [['type' => 'integer'], ['type' => 'null']]], 'site' => 'shipped'],
        ],
        'AccountState' => [
            'totals' => ['document' => ['type' => 'object', 'additionalProperties' => true], 'site' => ['seats' => 3]],
        ],
    ];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function siteOverrides(): iterable
    {
        foreach (self::AS_THE_SITE_SENDS as $schema => $fields) {
            foreach (array_keys($fields) as $field) {
                yield $schema . '.' . $field => [$schema, $field];
            }
        }
    }

    #[DataProvider('siteOverrides')]
    public function testEachFieldSampledAsTheSiteSendsItIsStillDeclaredOtherwise(string $schema, string $field): void
    {
        self::assertSame(Document::map(Document::map(self::AS_THE_SITE_SENDS[$schema][$field] ?? null)['document'] ?? null), self::declared($schema, $field), sprintf(
            'The pinned document now declares %s.%s otherwise than the override in AS_THE_SITE_SENDS was written against: drop the entry if the document agrees with what the site sends, or rewrite it.',
            $schema,
            $field,
        ));
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function models(): iterable
    {
        foreach (self::classes() as $class) {
            $reflection = new ReflectionClass($class);
            if ($reflection->hasConstant('SCHEMA') && str_starts_with($class, SourceTokens::ROOT_NAMESPACE . 'Model\\')) {
                $schema = $reflection->getConstant('SCHEMA');
                self::assertIsString($schema);
                yield $reflection->getShortName() => [$class, $schema];
            }
        }
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('models')]
    public function testAModelReadsOnlyAndAllOfWhatItsSchemaDefines(string $class, string $schema): void
    {
        $fields = Fields::of(self::sampleOf($schema), $schema);
        $from = [$class, 'from'];
        self::assertIsCallable($from);
        $from($fields);

        self::assertReadsMatch($schema, $fields->reads(), $class);
    }

    /**
     * @return iterable<string, array{class-string, string, class-string}>
     */
    public static function syncSchemas(): iterable
    {
        foreach ([SyncPull::class => [SyncPull::SCHEMAS, 'upserts'], SyncPush::class => [SyncPush::SCHEMAS, 'results'], SyncResult::class => [SyncResult::SCHEMAS, 'server_record']] as $class => [$schemas, $recordField]) {
            foreach ($schemas as $schema) {
                yield $schema => [$class, $schema, self::recordModel($schema, $recordField)];
            }
        }
    }

    /**
     * @param class-string $class
     * @param class-string $record
     */
    #[DataProvider('syncSchemas')]
    public function testASyncModelReadsOnlyAndAllOfWhatEachOfItsSchemasDefines(string $class, string $schema, string $record): void
    {
        $fields = Fields::of(self::sampleOf($schema), $schema);
        $from = [$class, 'from'];
        $read = [$record, 'from'];
        self::assertIsCallable($from);
        self::assertIsCallable($read);
        $from($fields, \Closure::fromCallable($read));

        self::assertReadsMatch($schema, $fields->reads(), $class);
    }

    public function testEverySchemaASuccessAnswersIsReadByAModel(): void
    {
        $read = [];
        foreach (self::models() as [, $schema]) {
            $read[] = $schema;
        }
        $read = [...$read, ...SyncPull::SCHEMAS, ...SyncPush::SCHEMAS, ...SyncResult::SCHEMAS];

        foreach (Document::operations() as $id => $documented) {
            $success = [];
            foreach (Document::map($documented['operation']['responses'] ?? null) as $code => $response) {
                if ((int) $code === Operation::from($id)->successStatus()) {
                    $success = Document::map(Document::map($response)['content'] ?? null);
                }
            }
            $schema = Document::map(Document::map($success['application/json'] ?? null)['schema'] ?? null);
            $name = Document::refName($schema);
            if ($name === null) {
                continue;
            }

            foreach (self::answeredSchemas($name) as $answered) {
                self::assertContains($answered, $read, sprintf('%s answers the schema %s, which no model reads.', $id, $answered));
            }
        }
    }

    /**
     * Each method marked with the operation it calls, and the clients
     * (AdministratorApi, MemberApi) it is reached from.
     *
     * @return list<array{operation: Operation, method: string, roots: list<class-string>}>
     */
    private static function endpoints(): array
    {
        $roots = [];
        foreach ([AdministratorApi::class, MemberApi::class] as $root) {
            foreach (self::reachable($root) as $class) {
                $roots[$class][] = $root;
            }
        }

        $endpoints = [];
        foreach (self::classes() as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                foreach ($method->getAttributes(Endpoint::class) as $attribute) {
                    $endpoints[] = ['operation' => $attribute->newInstance()->operation, 'method' => $class . '::' . $method->getName(), 'roots' => $roots[$class] ?? []];
                }
            }
        }

        return $endpoints;
    }

    /**
     * The classes a caller reaches from a client through the public
     * methods that return one of the package's resource groups.
     *
     * @param class-string $root
     * @return list<class-string>
     */
    private static function reachable(string $root): array
    {
        $seen = [$root => true];
        $queue = [$root];
        while ($queue !== []) {
            $class = array_shift($queue);
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();
                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }
                $returned = $type->getName();
                if (str_starts_with($returned, SourceTokens::ROOT_NAMESPACE . 'Resource\\') && !isset($seen[$returned]) && class_exists($returned)) {
                    $seen[$returned] = true;
                    $queue[] = $returned;
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * @return list<class-string>
     */
    private static function classes(): array
    {
        $classes = [];
        foreach (SourceTokens::classNames() as $class) {
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }

    /**
     * The model whose schema the records of a sync schema follow.
     *
     * @return class-string
     */
    private static function recordModel(string $schema, string $field): string
    {
        $property = Document::map(Document::map(Document::schema($schema)['properties'] ?? null)[$field] ?? null);
        $items = isset($property['items']) ? Document::map($property['items']) : $property;
        foreach (Document::list($items['oneOf'] ?? []) as $alternative) {
            if ((Document::map($alternative)['type'] ?? null) !== 'null') {
                $items = Document::map($alternative);
            }
        }
        $name = Document::refName($items);
        self::assertNotNull($name, sprintf('%s.%s names no schema.', $schema, $field));
        if (in_array($name, SyncResult::SCHEMAS, true)) {
            return self::recordModel($name, 'server_record');
        }

        foreach (self::models() as [$class, $modelSchema]) {
            if ($modelSchema === $name) {
                return $class;
            }
        }
        self::fail(sprintf('No model reads %s, the records of %s.', $name, $schema));
    }

    /**
     * The schemas a success answer of the named schema carries a model of:
     * the items of a page, or the schema itself.
     *
     * @return list<string>
     */
    private static function answeredSchemas(string $name): array
    {
        $schema = Document::schema($name);
        $properties = Document::map($schema['properties'] ?? null);
        if (isset($properties['data']) && array_diff(array_keys($properties), ['data', 'next_cursor']) === []) {
            $items = Document::refName(Document::map(Document::map($properties['data'])['items'] ?? null));

            return $items === null ? [] : [$items];
        }

        return [$name];
    }

    /**
     * @param list<string> $reads
     */
    private static function assertReadsMatch(string $schema, array $reads, string $class): void
    {
        foreach ($reads as $path) {
            self::assertNotNull(self::schemaAt(Document::schema($schema), $path), sprintf('%s reads "%s", which the %s schema does not define.', $class, $path, $schema));
        }

        foreach (self::requiredPaths(Document::schema($schema), '', $reads) as $required) {
            self::assertContains($required, $reads, sprintf('%s does not read "%s", which the %s schema requires.', $class, $required, $schema));
        }
    }

    /**
     * The schema at a read path (`items[].price`), or null when the
     * document defines nothing there.
     *
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>|null
     */
    private static function schemaAt(array $schema, string $path): ?array
    {
        $node = $schema;
        foreach (explode('.', $path) as $segment) {
            $list = str_ends_with($segment, '[]');
            $name = $list ? substr($segment, 0, -2) : $segment;
            $properties = Document::map(self::nonNull(Document::resolve($node))['properties'] ?? null);
            if (!isset($properties[$name])) {
                return null;
            }
            $node = Document::map($properties[$name]);
            if ($list) {
                $node = Document::map(self::nonNull(Document::resolve($node))['items'] ?? null);
            }
        }

        return $node;
    }

    /**
     * The required fields of an object schema and, for each nested object
     * the reads went into, of that object too.
     *
     * @param array<array-key, mixed> $schema
     * @param list<string> $reads
     * @return list<string>
     */
    private static function requiredPaths(array $schema, string $prefix, array $reads): array
    {
        $schema = self::nonNull(Document::resolve($schema));
        $properties = Document::map($schema['properties'] ?? null);
        $paths = [];
        foreach (Document::list($schema['required'] ?? []) as $name) {
            if (is_string($name)) {
                $paths[] = $prefix . $name;
            }
        }

        foreach ($properties as $name => $property) {
            $property = self::nonNull(Document::resolve(Document::map($property)));
            $isList = isset($property['items']);
            $path = $prefix . $name . ($isList ? '[]' : '');
            $entered = array_filter($reads, static fn(string $read): bool => str_starts_with($read, $path . '.'));
            if ($entered === []) {
                continue;
            }
            $paths = [...$paths, ...self::requiredPaths($isList ? Document::map($property['items']) : $property, $path . '.', $reads)];
        }

        return $paths;
    }

    /**
     * The non-null alternative of a `oneOf: [T, null]`, or the schema itself.
     *
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>
     */
    private static function nonNull(array $schema): array
    {
        foreach (Document::list($schema['oneOf'] ?? $schema['anyOf'] ?? []) as $alternative) {
            $alternative = Document::map($alternative);
            if (($alternative['type'] ?? null) !== 'null') {
                return Document::resolve($alternative);
            }
        }

        return $schema;
    }

    /**
     * A sample object of a named schema, with the fields the document types
     * otherwise than the site sends them sampled as the site sends them.
     *
     * @return array<array-key, mixed>
     */
    private static function sampleOf(string $name): array
    {
        return [...Document::map(self::sample(Document::schema($name))), ...self::siteOverridesOf($name)];
    }

    /**
     * The fields of a schema to sample as the site sends them. Whether the
     * document still declares what each override was written against is
     * the test of AS_THE_SITE_SENDS, not this.
     *
     * @return array<array-key, mixed>
     */
    private static function siteOverridesOf(string $name): array
    {
        $overrides = [];
        foreach (Document::map(self::AS_THE_SITE_SENDS[$name] ?? null) as $field => $override) {
            $overrides[$field] = Document::map($override)['site'] ?? null;
        }

        return $overrides;
    }

    /**
     * What the document declares for a field of a schema, its description
     * aside.
     *
     * @return array<array-key, mixed>
     */
    private static function declared(string $schema, string $field): array
    {
        $declared = Document::map(Document::map(Document::schema($schema)['properties'] ?? null)[$field] ?? null);
        unset($declared['description']);

        return $declared;
    }

    /**
     * A value of the schema with every property present, so a model's
     * every read lands on something of the documented type.
     *
     * @param array<array-key, mixed> $schema
     */
    private static function sample(array $schema): mixed
    {
        $name = Document::refName($schema);
        if ($name !== null) {
            return self::sampleOf($name);
        }
        $schema = self::nonNull(Document::resolve($schema));
        $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : null);
        if (is_array($type)) {
            $type = array_values(array_filter($type, static fn(mixed $member): bool => $member !== 'null'))[0] ?? null;
        }
        $enum = Document::list($schema['enum'] ?? []);

        return match ($type) {
            'string' => $enum[0] ?? (($schema['format'] ?? null) === 'date-time' ? '2026-10-08T12:34:56Z' : 'x'),
            'integer' => $enum[0] ?? 1,
            'number' => 1.5,
            'boolean' => true,
            'array' => [self::sample(Document::map($schema['items'] ?? ['type' => 'string']))],
            'object' => isset($schema['properties'])
                ? array_map(static fn(mixed $property): mixed => self::sample(Document::map($property)), Document::map($schema['properties']))
                : ['key' => 'value'],
            default => 'x',
        };
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Api\AdministratorApi;
use Appsolutely\Sdk\Api\Audience;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Http\MediaType;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\SyncPull;
use Appsolutely\Sdk\Model\SyncPush;
use Appsolutely\Sdk\Model\SyncResult;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use DateTimeImmutable;
use DateTimeInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Holds the typed client to the pinned Site API document: every operation
 * has exactly one method, every method names an operation and path the
 * document has and sits on the client of the operation's audience, every
 * request a method sends, on every page, carries the query parameters its
 * operation documents and no others and cannot leave out a required one,
 * and every model reads only fields its schema defines and every field the
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
     * Arguments a method checks before it sends, by parameter name; every
     * other argument is its type's plain sample (see argument()).
     */
    private const array ARGUMENTS = [
        'order' => 'asc',
        'store' => 'app_store',
        'storefront' => 'US',
        'status' => 'failed',
    ];

    /**
     * The query name a cursor list carries from its second page on.
     */
    private const string CURSOR = 'cursor';

    /**
     * The query names a method sends are learnt by calling it against an
     * HTTP client that keeps each request as it went over the wire, once with
     * every argument given and once with only the required ones; a list is
     * walked to its second page. Each request is held to the document on its
     * own, so a later page that drops a filter the first page sent fails. The
     * names are read from the raw query, so a list's must carry its brackets.
     */
    #[DataProvider('documentedOperations')]
    public function testEveryMethodSendsTheQueryItsOperationDocuments(string $id): void
    {
        $endpoint = self::endpointOf($id);
        $paged = $endpoint['operation']->isCursorList();
        $documented = [];
        foreach (Document::operations()[$id]['parameters'] as $parameter) {
            if (($parameter['in'] ?? null) === 'query' && is_string($parameter['name'] ?? null)) {
                $documented[$parameter['name']] = $parameter;
            }
        }

        $full = self::drive($endpoint, optional: true);
        foreach ($full as $request => $query) {
            $sent = self::names($query);
            $expected = [];
            foreach (array_keys($documented) as $name) {
                if (!$paged || $name !== self::CURSOR || $request > 0) {
                    $expected[] = $name;
                }
            }
            sort($expected);
            self::assertSame($expected, $sent, sprintf('%s sends the query %s in request %d when every argument is given; the document gives %s %s.', $endpoint['method'], self::shown($sent), $request + 1, $id, self::shown($expected)));
        }

        foreach (self::drive($endpoint, optional: false) as $request => $query) {
            $sent = self::names($query);
            self::assertSame([], array_values(array_diff($sent, array_keys($documented))), sprintf('%s sends the query %s in request %d when only its required arguments are given; the document gives %s %s.', $endpoint['method'], self::shown($sent), $request + 1, $id, self::shown(array_keys($documented))));
            foreach ($documented as $name => $parameter) {
                if (($parameter['required'] ?? false) === true) {
                    self::assertContains($name, $sent, sprintf('%s leaves out "%s", which %s requires, from request %d when only its required arguments are given.', $endpoint['method'], $name, $id, $request + 1));
                }
            }
            if ($paged) {
                self::assertSame($request > 0, in_array(self::CURSOR, $sent, true), sprintf('%s sends "%s" in request %d; a list sends it from its second page on.', $endpoint['method'], self::CURSOR, $request + 1));
            }
        }

        foreach ($documented as $name => $parameter) {
            if ((Document::map($parameter['schema'] ?? null)['type'] ?? null) !== 'array') {
                continue;
            }
            self::assertStringEndsWith('[]', $name, sprintf('%s types "%s" an array, whose name the SDK sends with brackets.', $id, $name));
            foreach ($full as $request => $query) {
                self::assertCount(count(Document::list(self::argument('string[]'))), array_keys($query, $name, true), sprintf('%s does not send each value of "%s" under that name in request %d.', $endpoint['method'], $name, $request + 1));
            }
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
     * The document leaves an account's `totals` an open object; the site
     * sends a quantity per key.
     */
    private const array AS_THE_SITE_SENDS = [
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
     * @return array{operation: Operation, method: string, roots: list<class-string>}
     */
    private static function endpointOf(string $id): array
    {
        foreach (self::endpoints() as $endpoint) {
            if ($endpoint['operation']->value === $id) {
                return $endpoint;
            }
        }
        self::fail(sprintf('%s has no SDK method.', $id));
    }

    /**
     * Calls a method against a site that answers every request in memory
     * and returns the query names of each request it sent, in order and
     * with repeats. Every argument is given when `$optional`, only the
     * required ones otherwise.
     *
     * Every answer is a readable page. A list's first page names a next one
     * and its second names none, so the walk reaches the second page and
     * ends there; a list that throws on the way, or sends any other number
     * of requests, fails. Any other method sends one request, and may then
     * throw on reading a page where it expects its own answer: that is the
     * expected end of its run, as only what it sent is asked.
     *
     * @param array{operation: Operation, method: string, roots: list<class-string>} $endpoint
     * @return non-empty-list<list<string>>
     */
    private static function drive(array $endpoint, bool $optional): array
    {
        $site = new class ($endpoint['operation']->successStatus()) implements ClientInterface {
            /** @var list<list<string>> */
            public array $queries = [];

            public function __construct(private readonly int $status) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $names = [];
                $query = $request->getUri()->getQuery();
                foreach ($query === '' ? [] : explode('&', $query) as $pair) {
                    $names[] = rawurldecode(explode('=', $pair, 2)[0]);
                }
                $page = $this->queries === [] ? ['data' => [], 'next_cursor' => 'next-page'] : ['data' => []];
                $this->queries[] = $names;

                return Psr17FactoryDiscovery::findResponseFactory()->createResponse($this->status)
                    ->withHeader(Header::CONTENT_TYPE, MediaType::JSON)
                    ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream(json_encode($page, JSON_THROW_ON_ERROR)));
            }
        };
        $client = new Client(new Config(
            baseUrl: 'https://site.test',
            issuer: 'https://site.test',
            clientId: 'contract-client',
            clientSecret: 'contract-client-secret',
            apiToken: 'contract-administrator-token',
            httpClient: $site,
            requestFactory: Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: Psr17FactoryDiscovery::findStreamFactory(),
            retryPolicy: RetryPolicy::none(),
        ));

        [$class, $name] = explode('::', $endpoint['method'], 2);
        self::assertTrue(class_exists($class));
        $root = ($endpoint['roots'][0] ?? null) === MemberApi::class ? $client->forMember('contract-member-token')->api() : $client->api();
        $method = new ReflectionMethod($class, $name);
        $arguments = [];
        foreach ($method->getParameters() as $parameter) {
            if (!$optional && $parameter->isOptional()) {
                break;
            }
            $type = $parameter->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type, sprintf('%s takes $%s, of no single type to sample.', $endpoint['method'], $parameter->getName()));
            $arguments[$parameter->getName()] = self::ARGUMENTS[$parameter->getName()] ?? self::argument($type->getName() === 'array' ? 'string[]' : $type->getName());
        }

        $paged = $endpoint['operation']->isCursorList();
        $result = null;
        $failure = null;
        try {
            $result = $method->invokeArgs(self::reach($root, $class), $arguments);
            if ($paged && $result instanceof Paginator) {
                foreach ($result as $item) {
                    // Walked only for the requests it sends.
                }
            }
        } catch (Throwable $thrown) {
            $failure = $thrown;
        }

        $queries = $site->queries;
        if ($queries === []) {
            self::fail(sprintf('%s sent no request: %s', $endpoint['method'], $failure === null ? 'it returned without one.' : $failure::class . ': ' . $failure->getMessage()));
        }
        if ($paged) {
            self::assertNull($failure, sprintf('%s threw after %d request(s), before its list was walked to the end: %s', $endpoint['method'], count($queries), $failure === null ? '' : $failure::class . ': ' . $failure->getMessage()));
            self::assertInstanceOf(Paginator::class, $result, sprintf('%s calls %s, a cursor list, without returning a Paginator.', $endpoint['method'], $endpoint['operation']->value));
        }
        self::assertCount($paged ? 2 : 1, $queries, sprintf('%s sent %d request(s); a list walked to its second page sends 2, any other method 1.', $endpoint['method'], count($queries)));

        return $queries;
    }

    /**
     * The distinct names of one request's query, sorted.
     *
     * @param list<string> $query
     * @return list<string>
     */
    private static function names(array $query): array
    {
        $names = array_values(array_unique($query));
        sort($names);

        return $names;
    }

    /**
     * A plain sample of a type: what a method is called with when
     * ARGUMENTS gives nothing by the parameter's name.
     */
    private static function argument(string $type): mixed
    {
        return match ($type) {
            'string' => 'x',
            'int' => 2,
            'bool' => true,
            'string[]' => ['x', 'y'],
            DateTimeInterface::class => new DateTimeImmutable('2026-10-08T12:34:56Z'),
            default => self::fail(sprintf('No sample of %s to call a method with; add one to argument() or ARGUMENTS.', $type)),
        };
    }

    /**
     * The resource group of a class reached from a client through public
     * methods that take nothing and return one.
     *
     * @param class-string $class
     */
    private static function reach(object $root, string $class): object
    {
        $seen = [];
        $queue = [$root];
        while ($queue !== []) {
            $group = array_shift($queue);
            if ($group instanceof $class) {
                return $group;
            }
            $seen[$group::class] = true;
            foreach ((new ReflectionClass($group))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();
                if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0 || !$type instanceof ReflectionNamedType || !str_starts_with($type->getName(), SourceTokens::ROOT_NAMESPACE . 'Resource\\') || isset($seen[$type->getName()])) {
                    continue;
                }
                $next = $method->invoke($group);
                self::assertIsObject($next);
                $queue[] = $next;
            }
        }
        self::fail(sprintf('%s is not reached from %s.', $class, $root::class));
    }

    /**
     * @param list<string> $names
     */
    private static function shown(array $names): string
    {
        return $names === [] ? 'none' : '"' . implode('", "', $names) . '"';
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

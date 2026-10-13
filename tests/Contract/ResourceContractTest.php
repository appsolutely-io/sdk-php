<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Api\AdministratorApi;
use Appsolutely\Sdk\Api\Audience;
use Appsolutely\Sdk\Api\BackendApi;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\MemberApi;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Http\MediaType;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\SyncPull;
use Appsolutely\Sdk\Model\SyncPush;
use Appsolutely\Sdk\Model\SyncResult;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use Appsolutely\Sdk\Tests\Support\UntypedRecord;
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
 * request a method sends goes to the method and path of the operation it is
 * marked with and, on every page, carries the query parameters its
 * operation documents and no others and cannot leave out a required one,
 * every method reads its answer as the schema the document gives it, and
 * every model reads only fields its schema defines and every field the
 * schema requires, each as the type and nullability it declares.
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
                Audience::Backend => [BackendApi::class],
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
        'value' => true,
        'platform' => 'ios',
        'osVersion' => '17.4',
    ];

    /**
     * Methods that send one argument whole as the body, so the member names
     * are the caller's: each method's array shape names the members, and the
     * document's request body is what that shape is written from.
     */
    private const array BODY_PASSED_THROUGH = ['createArticle', 'updateArticle', 'createMeAddress', 'updateMeAddress'];

    /**
     * The decoded body of each request the last drive() sent, null for none.
     *
     * @var list<mixed>
     */
    private static array $bodies = [];

    /**
     * What the last drive() threw, null when it returned.
     */
    private static ?Throwable $failure = null;

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
     * sends a quantity per key. It leaves a subscription's `pending_period` an
     * open object or null; the site sends the period's start and where its
     * payment stands.
     */
    private const array AS_THE_SITE_SENDS = [
        'AccountState' => [
            'totals' => ['document' => ['type' => 'object', 'additionalProperties' => true], 'site' => ['seats' => 3]],
        ],
        'AccountSubscription' => [
            'pending_period' => ['document' => ['type' => ['object', 'null'], 'additionalProperties' => true], 'site' => ['start' => '2026-05-01T00:00:00Z', 'status' => 'pending']],
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
     * Fields the pinned document declares required and never null that the
     * site may still send as null, where the model reads them as nullable,
     * so an answer carrying one is read rather than refused. Each applies
     * only while the document still declares the field so; one it comes to
     * declare nullable fails until the entry is dropped.
     *
     * The site writes an order's `updated_at` and a referral reward's
     * `earned_at` from record timestamps it may hold empty.
     *
     * An entry is dropped when the server documents the field as nullable
     * (this test then fails) or always sends it. Only the document is
     * pinned, not the site's source, so the second cannot be detected here:
     * the check runs one way, and a server that stops sending null leaves
     * the entry in place until it is dropped by hand.
     */
    private const array NULL_AS_THE_SITE_SENDS = [
        'Order' => ['updated_at'],
        'ReferralReward' => ['earned_at'],
    ];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function siteNulls(): iterable
    {
        foreach (self::NULL_AS_THE_SITE_SENDS as $schema => $fields) {
            foreach ($fields as $field) {
                yield $schema . '.' . $field => [$schema, $field];
            }
        }
    }

    #[DataProvider('siteNulls')]
    public function testEachFieldReadAsNullableAsTheSiteSendsItIsStillDeclaredRequired(string $schema, string $field): void
    {
        $declared = self::declaredType(Document::map(Document::map(Document::schema($schema)['properties'] ?? null)[$field] ?? null), $schema . '.' . $field);
        self::assertTrue(!$declared['nullable'] && in_array($field, Document::list(Document::schema($schema)['required'] ?? []), true), sprintf(
            'The pinned document now lets %s.%s be null or missing: the server documents what the model reads, so drop it from NULL_AS_THE_SITE_SENDS.',
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
        self::assertReadTypesMatch($schema, $fields->accessors(), $class);
    }

    /**
     * Every value the document enumerates for a field a model reads is a
     * constant of that model, named for the field and the value
     * (`STATUS_PAST_DUE = 'past_due'`), so a caller compares with a name
     * and a value the document adds fails here until it has one. The field
     * itself stays a plain string, so a value a newer site sends still reads.
     * Fields nested in the schema's own objects and lists count, under
     * their own name; a nested schema of its own is its model's. Every
     * other string constant of the model is one of those values, so a value
     * the document drops fails here until its constant goes too.
     *
     * @param class-string $class
     */
    #[DataProvider('models')]
    public function testEveryValueAModelsSchemaEnumeratesIsAConstantOfTheModel(string $class, string $schema): void
    {
        $enumerated = [];
        foreach (Document::map(Document::schema($schema)['properties'] ?? null) as $field => $declaration) {
            self::collectEnumerated((string) $field, Document::map($declaration), $schema . '.' . $field, $enumerated);
        }
        foreach (self::VALUES_AS_THE_SITE_SENDS[$schema] ?? [] as $field => $values) {
            foreach ($values as $value) {
                $enumerated[strtoupper($field . '_' . $value)] = $value;
            }
        }

        $constants = [];
        foreach ((new ReflectionClass($class))->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC) as $constant) {
            if ($constant->getDeclaringClass()->getName() === $class && $constant->getName() !== 'SCHEMA' && is_string($constant->getValue())) {
                $constants[$constant->getName()] = $constant->getValue();
            }
        }

        $missing = [];
        foreach ($enumerated as $name => $value) {
            if (($constants[$name] ?? null) !== $value) {
                $missing[] = sprintf('%s = \'%s\'', $name, $value);
            }
        }
        $stale = [];
        foreach ($constants as $name => $value) {
            if (($enumerated[$name] ?? null) !== $value) {
                $stale[] = sprintf('%s = \'%s\'', $name, $value);
            }
        }

        self::assertSame([], $missing, sprintf('%s reads %s, whose document enumerates values it has no constant for: %s.', $class, $schema, implode(', ', $missing)));
        self::assertSame([], $stale, sprintf('%s has constants for values %s no longer enumerates: %s.', $class, $schema, implode(', ', $stale)));
    }

    /**
     * Fields the pinned document leaves a plain string whose values the
     * site enumerates, where the model has a constant per value. Each
     * applies only while the document still enumerates nothing for the
     * field; once it does, the constants are checked against it and the
     * entry is dropped.
     *
     * A referral reward's `type` is the site's reward type enum.
     */
    private const array VALUES_AS_THE_SITE_SENDS = [
        'ReferralReward' => ['type' => ['credit', 'cashback']],
    ];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function siteValues(): iterable
    {
        foreach (self::VALUES_AS_THE_SITE_SENDS as $schema => $fields) {
            foreach (array_keys($fields) as $field) {
                yield $schema . '.' . $field => [$schema, $field];
            }
        }
    }

    #[DataProvider('siteValues')]
    public function testEachFieldWhoseValuesTheSiteEnumeratesIsStillUnenumerated(string $schema, string $field): void
    {
        $declared = self::nonNull(Document::resolve(Document::map(Document::map(Document::schema($schema)['properties'] ?? null)[$field] ?? null)));

        self::assertSame('string', $declared['type'] ?? null, sprintf('%s.%s is no longer a string.', $schema, $field));
        self::assertArrayNotHasKey('enum', $declared, sprintf('The pinned document now enumerates %s.%s: drop it from VALUES_AS_THE_SITE_SENDS.', $schema, $field));
    }

    /**
     * The constant each value enumerated for a field should have, the
     * field's own and those of the objects and lists inside it, stopping
     * at an object schema of its own.
     *
     * @param array<array-key, mixed> $declaration
     * @param string $where the field, for a failure
     * @param array<string, string> $enumerated
     */
    private static function collectEnumerated(string $field, array $declaration, string $where, array &$enumerated): void
    {
        $node = self::nonNull(Document::resolve($declaration));
        $named = Document::refName($declaration) !== null || Document::refName(Document::map(Document::list($declaration['oneOf'] ?? $declaration['anyOf'] ?? [])[0] ?? null)) !== null;
        if ($named && isset($node['properties'])) {
            return;
        }
        foreach (Document::list($node['enum'] ?? []) as $value) {
            if ($value === null) {
                continue;
            }
            self::assertIsString($value, sprintf('%s enumerates a value that is not a string.', $where));
            $enumerated[strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $field . '_' . $value))] = $value;
        }
        if (isset($node['items'])) {
            self::collectEnumerated($field, Document::map($node['items']), $where . '[]', $enumerated);
        }
        foreach (Document::map($node['properties'] ?? null) as $nested => $nestedDeclaration) {
            self::collectEnumerated((string) $nested, Document::map($nestedDeclaration), $where . '.' . $nested, $enumerated);
        }
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
        self::assertReadTypesMatch($schema, $fields->accessors(), $class);
    }

    /**
     * Each body is held to the operation's documented request body: given
     * every argument, the method sends every member the document names and
     * no other; given only its required ones, it still sends every member
     * the document requires. A member sent as null is one the document lets
     * be null.
     */
    #[DataProvider('documentedOperations')]
    public function testEveryMethodSendsTheBodyItsOperationDocuments(string $id): void
    {
        $endpoint = self::endpointOf($id);
        $documented = Document::map(Document::map(Document::map(Document::operations()[$id]['operation']['requestBody'] ?? null)['content'] ?? null)['application/json'] ?? null)['schema'] ?? null;

        if (in_array($id, self::BODY_PASSED_THROUGH, true)) {
            self::assertNotNull($documented, sprintf('%s is listed in BODY_PASSED_THROUGH but documents no request body.', $id));

            return;
        }

        self::drive($endpoint, optional: true);
        $full = self::$bodies[0] ?? null;

        if ($documented === null) {
            self::assertContains($full, [null, []], sprintf('%s sends a body; the document gives %s none.', $endpoint['method'], $id));

            return;
        }

        $schema = Document::resolve(Document::map($documented));
        $properties = Document::map($schema['properties'] ?? null);
        $required = Document::list($schema['required'] ?? []);

        self::assertIsArray($full, sprintf('%s sends no JSON object; the document gives %s a request body.', $endpoint['method'], $id));
        $sent = array_keys($full);
        sort($sent);
        $named = array_keys($properties);
        sort($named);
        self::assertSame($named, $sent, sprintf('%s sends the members %s when every argument is given; the document gives %s %s.', $endpoint['method'], self::shown($sent), $id, self::shown($named)));

        self::drive($endpoint, optional: false);
        $minimal = self::$bodies[0] ?? null;
        self::assertIsArray($minimal, sprintf('%s sends no JSON object when only its required arguments are given.', $endpoint['method']));
        foreach ($required as $member) {
            self::assertIsString($member);
            self::assertArrayHasKey($member, $minimal, sprintf('%s leaves out "%s", which %s requires, when only its required arguments are given.', $endpoint['method'], $member, $id));
        }
        foreach ([$full, $minimal] as $body) {
            foreach ($body as $member => $value) {
                self::assertArrayHasKey($member, $properties, sprintf('%s sends "%s", which the request body of %s does not name.', $endpoint['method'], $member, $id));
                if ($value === null) {
                    self::assertContains('null', (array) (Document::map($properties[$member])['type'] ?? []), sprintf('%s sends "%s" as null, which %s does not let be null.', $endpoint['method'], $member, $id));
                }
            }
        }
    }

    public function testEverySchemaASuccessAnswersIsReadByAModel(): void
    {
        $read = [];
        foreach (self::models() as [, $schema]) {
            $read[] = $schema;
        }
        $read = [...$read, ...SyncPull::SCHEMAS, ...SyncPush::SCHEMAS, ...SyncResult::SCHEMAS];

        foreach (array_keys(Document::operations()) as $id) {
            $name = self::successSchema($id);
            if ($name === null) {
                continue;
            }

            foreach (self::answeredSchemas($name) as $answered) {
                self::assertContains($answered, $read, sprintf('%s answers the schema %s, which no model reads.', $id, $answered));
            }
        }
    }

    /**
     * The schema a method reads its answer as is learnt by answering it with
     * an empty object, or a page of one empty item: the model refuses it at
     * its first required field, naming the schema it was handed. That must
     * be the schema the document gives the operation's success answer, or
     * the items of its page, so a method that reads its answer as another
     * schema fails, even where the two share their required fields.
     */
    #[DataProvider('documentedOperations')]
    public function testEveryMethodReadsItsAnswerAsTheSchemaItsOperationDocuments(string $id): void
    {
        $endpoint = self::endpointOf($id);
        $documented = self::successSchema($id);
        if ($documented === null) {
            self::addToAssertionCount(1);

            return;
        }
        $answered = self::answeredSchemas($documented);
        $page = $answered !== [$documented];
        self::assertCount(1, $answered, sprintf('%s answers %s, a page whose items name no schema.', $id, $documented));

        self::drive($endpoint, optional: false, answer: $page ? ['data' => [[]]] : []);
        $failure = self::$failure;
        self::assertInstanceOf(UnexpectedResponseException::class, $failure, sprintf('%s read an empty %s without refusing it, so the schema it reads it as is not learnt: %s', $endpoint['method'], $page ? 'page item' : 'object', $failure === null ? 'it returned.' : $failure::class . ': ' . $failure->getMessage()));
        self::assertMatchesRegularExpression(self::READ_AS, $failure->getMessage());
        preg_match(self::READ_AS, $failure->getMessage(), $match);

        self::assertSame($answered[0], $match[1] ?? null, sprintf('%s reads the answer of %s as the schema %s; the document gives it %s.', $endpoint['method'], $id, $match[1] ?? 'none', $answered[0]));
    }

    /**
     * How a model's refusal of an answer names the schema it read it as.
     */
    private const string READ_AS = '/^The site answered a "([^"]+)" object /';

    /**
     * The name of the schema an operation's success answers with as JSON, or
     * null when it answers none.
     */
    private static function successSchema(string $id): ?string
    {
        $success = [];
        foreach (Document::map(Document::operations()[$id]['operation']['responses'] ?? null) as $code => $response) {
            if ((int) $code === Operation::from($id)->successStatus()) {
                $success = Document::map(Document::map($response)['content'] ?? null);
            }
        }

        return Document::refName(Document::map(Document::map($success['application/json'] ?? null)['schema'] ?? null));
    }

    /**
     * Each method marked with the operation it calls, and the clients
     * (AdministratorApi, MemberApi, BackendApi) it is reached from.
     *
     * @return list<array{operation: Operation, method: string, roots: list<class-string>}>
     */
    private static function endpoints(): array
    {
        $roots = [];
        foreach ([AdministratorApi::class, MemberApi::class, BackendApi::class] as $root) {
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
     * Given an `$answer`, every request is answered with it instead and the
     * run may end in a throw wherever it reads it; what it threw is kept in
     * $failure.
     *
     * @param array{operation: Operation, method: string, roots: list<class-string>} $endpoint
     * @param array<array-key, mixed>|null $answer
     * @return non-empty-list<list<string>>
     */
    private static function drive(array $endpoint, bool $optional, ?array $answer = null): array
    {
        $site = new class ($endpoint['operation']->successStatus(), $answer) implements ClientInterface {
            /** @var list<list<string>> */
            public array $queries = [];

            /** @var list<mixed> */
            public array $bodies = [];

            /** @var list<string> each request's method and path, `GET /api/v1/pages` */
            public array $lines = [];

            /**
             * @param array<array-key, mixed>|null $answer
             */
            public function __construct(private readonly int $status, private readonly ?array $answer) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                // The backend's sign-in, which only a backend method makes
                // and which is not one of the calls held to the document.
                $path = $request->getUri()->getPath();
                if ($path === '/.well-known/openid-configuration' || $path === '/oauth/token') {
                    return Psr17FactoryDiscovery::findResponseFactory()->createResponse(200)
                        ->withHeader(Header::CONTENT_TYPE, MediaType::JSON)
                        ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream(json_encode($path === '/oauth/token' ? ['access_token' => 'contract-backend-token', 'token_type' => 'Bearer', 'expires_in' => 3600] : [
                            'issuer' => 'https://site.test',
                            'authorization_endpoint' => 'https://site.test/oauth/authorize',
                            'token_endpoint' => 'https://site.test/oauth/token',
                            'jwks_uri' => 'https://site.test/oauth/jwks.json',
                            'response_types_supported' => ['code'],
                            'subject_types_supported' => ['public'],
                            'id_token_signing_alg_values_supported' => ['RS256'],
                        ], JSON_THROW_ON_ERROR)));
                }

                $names = [];
                $query = $request->getUri()->getQuery();
                foreach ($query === '' ? [] : explode('&', $query) as $pair) {
                    $names[] = rawurldecode(explode('=', $pair, 2)[0]);
                }
                $page = $this->answer ?? ($this->queries === [] ? ['data' => [], 'next_cursor' => 'next-page'] : ['data' => []]);
                $this->queries[] = $names;
                $this->lines[] = $request->getMethod() . ' ' . $path;
                $body = (string) $request->getBody();
                $this->bodies[] = $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);

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
            backendClientId: 'contract-backend',
            backendClientSecret: 'contract-backend-secret',
        ));

        [$class, $name] = explode('::', $endpoint['method'], 2);
        self::assertTrue(class_exists($class));
        $root = match ($endpoint['roots'][0] ?? null) {
            MemberApi::class => $client->forMember('contract-member-token')->api(),
            BackendApi::class => $client->backend(),
            default => $client->api(),
        };
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
        self::$failure = $failure;
        if ($answer !== null) {
            return $queries;
        }
        if ($paged) {
            self::assertNull($failure, sprintf('%s threw after %d request(s), before its list was walked to the end: %s', $endpoint['method'], count($queries), $failure === null ? '' : $failure::class . ': ' . $failure->getMessage()));
            self::assertInstanceOf(Paginator::class, $result, sprintf('%s calls %s, a cursor list, without returning a Paginator.', $endpoint['method'], $endpoint['operation']->value));
        }
        self::assertCount($paged ? 2 : 1, $queries, sprintf('%s sent %d request(s); a list walked to its second page sends 2, any other method 1.', $endpoint['method'], count($queries)));
        $declared = Document::operations()[$endpoint['operation']->value] ?? null;
        self::assertNotNull($declared, sprintf('%s is marked with %s, which the document does not have.', $endpoint['method'], $endpoint['operation']->value));
        foreach ($site->lines as $index => $line) {
            self::assertMatchesRegularExpression(self::requestLine($declared['method'], $declared['path']), $line, sprintf('%s is marked with %s (%s %s) but sent %s in request %d.', $endpoint['method'], $endpoint['operation']->value, $declared['method'], $declared['path'], $line, $index + 1));
        }

        self::$bodies = $site->bodies;

        return $queries;
    }

    /**
     * The pattern of a request line the document's method and path template
     * allow: each `{placeholder}` filled with one non-empty path segment.
     */
    private static function requestLine(string $method, string $template): string
    {
        $parts = preg_split('/\{[^}\/]+\}/', $template);
        self::assertIsArray($parts);

        return '#^' . preg_quote($method, '#') . ' ' . implode('[^/]+', array_map(static fn(string $part): string => preg_quote($part, '#'), $parts)) . '$#D';
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
        if ($name === null && in_array('object', (array) ($items['type'] ?? []), true) && ($items['additionalProperties'] ?? false) === true) {
            return UntypedRecord::class;
        }
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
     * What each accessor of Fields reads: the JSON types it takes (a `[]`
     * suffix for a list of them), the format a string must have, and
     * whether it reads a missing or null field as null.
     */
    private const array ACCESSORS = [
        'string' => ['type' => 'string', 'nullable' => false],
        'nullableString' => ['type' => 'string', 'nullable' => true],
        'int' => ['type' => 'integer', 'nullable' => false],
        'nullableInt' => ['type' => 'integer', 'nullable' => true],
        'bool' => ['type' => 'boolean', 'nullable' => false],
        'nullableBool' => ['type' => 'boolean', 'nullable' => true],
        'time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => false],
        'nullableTime' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
        'strings' => ['type' => 'string[]', 'nullable' => false],
        'map' => ['type' => 'object', 'nullable' => false],
        'nullableMap' => ['type' => 'object', 'nullable' => true],
        'intMap' => ['type' => 'object', 'nullable' => false],
        'object' => ['type' => 'object', 'nullable' => false],
        'nullableObject' => ['type' => 'object', 'nullable' => true],
        'objects' => ['type' => 'object[]', 'nullable' => false],
        'nullableObjects' => ['type' => 'object[]', 'nullable' => true],
    ];

    /**
     * Each field is read with the accessor its declaration calls for: of its
     * type (a date-time as a time), and as nullable exactly when the schema
     * lets it be null or leaves it out of `required`. A nullable read of a
     * field always sent would type the model looser than the site; a plain
     * read of one that may be null or missing would refuse a valid answer.
     * `any` reads a field whatever it is, and beneath a field sampled as
     * the site sends it the document declares nothing to compare with.
     *
     * @param array<string, list<string>> $accessors
     */
    private static function assertReadTypesMatch(string $schema, array $accessors, string $class): void
    {
        $mismatches = [];
        foreach ($accessors as $path => $used) {
            $parent = str_contains($path, '.') ? self::schemaAt(Document::schema($schema), substr($path, 0, (int) strrpos($path, '.'))) : Document::schema($schema);
            $node = self::schemaAt(Document::schema($schema), $path);
            if ($parent === null || $node === null || self::sampledAsTheSiteSends($parent) || self::sampledAsTheSiteSends($node)) {
                continue;
            }
            $field = substr($path, (int) strrpos('.' . $path, '.'));
            $name = str_ends_with($field, '[]') ? substr($field, 0, -2) : $field;
            $required = in_array($name, Document::list(self::nonNull(Document::resolve($parent))['required'] ?? []), true);
            if (array_diff($used, ['any']) === []) {
                continue;
            }
            $declaration = Document::map(Document::map(self::nonNull(Document::resolve($parent))['properties'] ?? null)[$name] ?? null);
            $declared = self::declaredType($declaration, $schema . '.' . $path);
            $owner = str_contains($path, '.') ? self::refOf($parent) : $schema;
            if ($owner !== null && in_array($name, self::NULL_AS_THE_SITE_SENDS[$owner] ?? [], true)) {
                $declared['nullable'] = true;
            }

            foreach (array_unique($used) as $accessor) {
                if ($accessor === 'any') {
                    continue;
                }
                $reads = self::ACCESSORS[$accessor] ?? self::fail(sprintf('No entry in ACCESSORS for Fields::%s().', $accessor));
                $expected = ['type' => $reads['type'], 'format' => $reads['format'] ?? null, 'nullable' => $reads['nullable']];
                $actual = ['type' => $declared['type'], 'format' => $declared['format'] === 'date-time' ? 'date-time' : null, 'nullable' => $declared['nullable'] || !$required];
                if ($actual === $expected) {
                    continue;
                }
                $mismatches[] = sprintf(
                    '%s reads "%s" of %s with %s(), which reads %s%s%s; the schema declares %s%s%s.',
                    $class,
                    $path,
                    $schema,
                    $accessor,
                    $expected['nullable'] ? 'a nullable ' : 'a ',
                    $expected['type'],
                    $expected['format'] === null ? '' : ' (' . $expected['format'] . ')',
                    $actual['nullable'] ? ($declared['nullable'] ? 'a nullable ' : 'an optional ') : 'a required ',
                    $actual['type'],
                    $actual['format'] === null ? '' : ' (' . $actual['format'] . ')',
                );
            }
        }

        self::assertSame([], $mismatches, implode("\n", $mismatches));
    }

    /**
     * The name of the component a node is, or is `oneOf` with null.
     *
     * @param array<array-key, mixed> $node
     */
    private static function refOf(array $node): ?string
    {
        foreach ([$node, ...Document::list($node['oneOf'] ?? $node['anyOf'] ?? [])] as $alternative) {
            $name = Document::refName(Document::map($alternative));
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The JSON type of a field's declaration (`object[]` for a list of
     * objects), its string format, and whether it may be null.
     *
     * @param array<array-key, mixed> $declaration
     * @param string $where the field, for a failure: `Order.lines[].price`
     * @return array{type: string, format: string|null, nullable: bool}
     */
    private static function declaredType(array $declaration, string $where): array
    {
        $nullable = false;
        $alternatives = Document::list($declaration['oneOf'] ?? $declaration['anyOf'] ?? []);
        if ($alternatives !== []) {
            foreach ($alternatives as $alternative) {
                if ((Document::map($alternative)['type'] ?? null) === 'null') {
                    $nullable = true;
                }
            }
        }
        $node = self::nonNull(Document::resolve($declaration));
        $types = (array) ($node['type'] ?? (isset($node['properties']) || isset($node['additionalProperties']) ? 'object' : []));
        if (in_array('null', $types, true)) {
            $nullable = true;
        }
        $types = array_values(array_filter($types, static fn(mixed $type): bool => $type !== 'null'));
        self::assertCount(1, $types, sprintf('%s declares %s types to compare an accessor with.', $where, $types === [] ? 'no' : 'several'));
        $type = $types[0];
        self::assertIsString($type);
        if ($type === 'array') {
            $items = self::declaredType(Document::map($node['items'] ?? null), $where . '[]');

            return ['type' => $items['type'] . '[]', 'format' => null, 'nullable' => $nullable];
        }
        $format = $node['format'] ?? null;

        return ['type' => $type, 'format' => is_string($format) ? $format : null, 'nullable' => $nullable];
    }

    /**
     * Whether a node is the declaration of a field AS_THE_SITE_SENDS samples
     * otherwise than the document, which checks that it still declares it so.
     *
     * @param array<array-key, mixed> $node
     */
    private static function sampledAsTheSiteSends(array $node): bool
    {
        unset($node['description']);

        foreach (self::AS_THE_SITE_SENDS as $fields) {
            foreach ($fields as $override) {
                if (Document::map($override['document']) === $node) {
                    return true;
                }
            }
        }

        return false;
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
                // Beneath a field sampled as the site sends it, the document
                // declares nothing to hold a read to.
                return self::sampledAsTheSiteSends($node) ? $node : null;
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

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

/**
 * Holds what a model reads to the schema it is read as: every read lands on
 * a field the schema defines, every required field is read, and each with
 * the accessor its declaration calls for. Also samples a schema, every
 * property present and of its documented type, to read a model from.
 */
trait SchemaReads
{
    /**
     * Fields the pinned document types otherwise than the site sends them,
     * where the model follows the site: sampled as the site sends them. Each
     * names what the document declares (its description aside), and the
     * override applies only while the document still declares exactly that,
     * so a document that changes, to agree with the site or otherwise, fails
     * until the entry is dropped or rewritten.
     *
     * The document leaves a subscription's `pending_period` an open object or
     * null; the site sends the period's start and where its payment stands.
     */
    /**
     * The pinned document the schemas are read from; a test of another
     * document's models names that document's class.
     *
     * @return class-string<Document>
     */
    protected static function document(): string
    {
        return Document::class;
    }

    private const array AS_THE_SITE_SENDS = [
        'AccountSubscription' => [
            'pending_period' => ['document' => ['type' => ['object', 'null'], 'additionalProperties' => true], 'site' => ['start' => '2026-05-01T00:00:00Z', 'status' => 'pending']],
        ],
    ];

    /**
     * @param list<string> $reads
     */
    private static function assertReadsMatch(string $schema, array $reads, string $class): void
    {
        foreach ($reads as $path) {
            self::assertNotNull(self::schemaAt(static::document()::schema($schema), $path), sprintf('%s reads "%s", which the %s schema does not define.', $class, $path, $schema));
        }

        foreach (self::requiredPaths(static::document()::schema($schema), '', $reads) as $required) {
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
            $parent = str_contains($path, '.') ? self::schemaAt(static::document()::schema($schema), substr($path, 0, (int) strrpos($path, '.'))) : static::document()::schema($schema);
            $node = self::schemaAt(static::document()::schema($schema), $path);
            if ($parent === null || $node === null || self::sampledAsTheSiteSends($parent) || self::sampledAsTheSiteSends($node)) {
                continue;
            }
            $field = substr($path, (int) strrpos('.' . $path, '.'));
            $name = str_ends_with($field, '[]') ? substr($field, 0, -2) : $field;
            $required = in_array($name, static::document()::list(self::nonNull(static::document()::resolve($parent))['required'] ?? []), true);
            if (array_diff($used, ['any']) === []) {
                continue;
            }
            $declaration = static::document()::map(static::document()::map(self::nonNull(static::document()::resolve($parent))['properties'] ?? null)[$name] ?? null);
            $declared = self::declaredType($declaration, $schema . '.' . $path);

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
        $alternatives = static::document()::list($declaration['oneOf'] ?? $declaration['anyOf'] ?? []);
        if ($alternatives !== []) {
            foreach ($alternatives as $alternative) {
                if ((static::document()::map($alternative)['type'] ?? null) === 'null') {
                    $nullable = true;
                }
            }
        }
        $node = self::nonNull(static::document()::resolve($declaration));
        $types = (array) ($node['type'] ?? (isset($node['properties']) || isset($node['additionalProperties']) ? 'object' : []));
        if (in_array('null', $types, true)) {
            $nullable = true;
        }
        $types = array_values(array_filter($types, static fn(mixed $type): bool => $type !== 'null'));
        self::assertCount(1, $types, sprintf('%s declares %s types to compare an accessor with.', $where, $types === [] ? 'no' : 'several'));
        $type = $types[0];
        self::assertIsString($type);
        if ($type === 'array') {
            $items = self::declaredType(static::document()::map($node['items'] ?? null), $where . '[]');

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
                if (static::document()::map($override['document']) === $node) {
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
            $properties = static::document()::map(self::nonNull(static::document()::resolve($node))['properties'] ?? null);
            if (!isset($properties[$name])) {
                // Beneath a field sampled as the site sends it, the document
                // declares nothing to hold a read to.
                return self::sampledAsTheSiteSends($node) ? $node : null;
            }
            $node = static::document()::map($properties[$name]);
            if ($list) {
                $node = static::document()::map(self::nonNull(static::document()::resolve($node))['items'] ?? null);
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
        $schema = self::nonNull(static::document()::resolve($schema));
        $properties = static::document()::map($schema['properties'] ?? null);
        $paths = [];
        foreach (static::document()::list($schema['required'] ?? []) as $name) {
            if (is_string($name)) {
                $paths[] = $prefix . $name;
            }
        }

        foreach ($properties as $name => $property) {
            $property = self::nonNull(static::document()::resolve(static::document()::map($property)));
            $isList = isset($property['items']);
            $path = $prefix . $name . ($isList ? '[]' : '');
            $entered = array_filter($reads, static fn(string $read): bool => str_starts_with($read, $path . '.'));
            if ($entered === []) {
                continue;
            }
            $paths = [...$paths, ...self::requiredPaths($isList ? static::document()::map($property['items']) : $property, $path . '.', $reads)];
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
        foreach (static::document()::list($schema['oneOf'] ?? $schema['anyOf'] ?? []) as $alternative) {
            $alternative = static::document()::map($alternative);
            if (($alternative['type'] ?? null) !== 'null') {
                return static::document()::resolve($alternative);
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
        return [...static::document()::map(self::sample(static::document()::schema($name))), ...self::siteOverridesOf($name)];
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
        foreach (static::document()::map(self::AS_THE_SITE_SENDS[$name] ?? null) as $field => $override) {
            $overrides[$field] = static::document()::map($override)['site'] ?? null;
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
        $declared = static::document()::map(static::document()::map(static::document()::schema($schema)['properties'] ?? null)[$field] ?? null);
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
        $name = static::document()::refName($schema);
        if ($name !== null) {
            return self::sampleOf($name);
        }
        if (isset($schema['allOf'])) {
            // Each part is sampled on its own, so a part that is a named
            // schema is sampled with its fields as the site sends them.
            $sample = [];
            foreach (static::document()::list($schema['allOf']) as $part) {
                $sample = [...$sample, ...static::document()::map(self::sample(static::document()::map($part)))];
            }

            return $sample;
        }
        $schema = self::nonNull(static::document()::resolve($schema));
        $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : null);
        if (is_array($type)) {
            $type = array_values(array_filter($type, static fn(mixed $member): bool => $member !== 'null'))[0] ?? null;
        }
        $enum = static::document()::list($schema['enum'] ?? []);

        return match ($type) {
            'string' => $enum[0] ?? (($schema['format'] ?? null) === 'date-time' ? '2026-10-08T12:34:56Z' : 'x'),
            'integer' => $enum[0] ?? 1,
            'number' => 1.5,
            'boolean' => true,
            'array' => [self::sample(static::document()::map($schema['items'] ?? ['type' => 'string']))],
            'object' => isset($schema['properties'])
                ? array_map(static fn(mixed $property): mixed => self::sample(static::document()::map($property)), static::document()::map($schema['properties']))
                : ['key' => is_array($schema['additionalProperties'] ?? null) ? self::sample(static::document()::map($schema['additionalProperties'])) : 'value'],
            default => 'x',
        };
    }
}

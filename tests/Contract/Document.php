<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * The pinned Site API document, read for the contract tests.
 */
final class Document
{
    public const array METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /** @var array<array-key, mixed>|null */
    private static ?array $document = null;

    /**
     * @return array<array-key, mixed>
     */
    public static function get(): array
    {
        if (self::$document === null) {
            $document = Yaml::parseFile(__DIR__ . '/openapi.yaml');
            if (!is_array($document)) {
                throw new RuntimeException('The pinned document is not a YAML mapping.');
            }
            self::$document = $document;
        }

        return self::$document;
    }

    /**
     * Every operation, keyed by operationId.
     *
     * @return array<string, array{method: string, path: string, operation: array<array-key, mixed>, parameters: list<array<array-key, mixed>>}>
     */
    public static function operations(): array
    {
        $operations = [];
        foreach (self::map(self::get()['paths'] ?? null) as $path => $item) {
            $item = self::map($item);
            foreach ($item as $method => $operation) {
                if (!in_array($method, self::METHODS, true)) {
                    continue;
                }
                $operation = self::map($operation);
                $id = $operation['operationId'] ?? null;
                if (!is_string($id)) {
                    throw new RuntimeException(sprintf('%s %s has no operationId.', $method, $path));
                }
                $parameters = [];
                foreach ([...self::list($item['parameters'] ?? []), ...self::list($operation['parameters'] ?? [])] as $parameter) {
                    $parameters[] = self::resolve(self::map($parameter));
                }
                $operations[$id] = ['method' => strtoupper($method), 'path' => $path, 'operation' => $operation, 'parameters' => $parameters];
            }
        }

        return $operations;
    }

    /**
     * A component schema by name.
     *
     * @return array<array-key, mixed>
     */
    public static function schema(string $name): array
    {
        $schemas = self::map(self::map(self::get()['components'] ?? null)['schemas'] ?? null);
        if (!isset($schemas[$name])) {
            throw new RuntimeException(sprintf('The document has no schema "%s".', $name));
        }

        return self::map($schemas[$name]);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function schemas(): array
    {
        return self::map(self::map(self::get()['components'] ?? null)['schemas'] ?? null);
    }

    /**
     * Every webhook the document describes, keyed by event type.
     *
     * @return array<string, array<array-key, mixed>>
     */
    public static function webhooks(): array
    {
        $webhooks = [];
        foreach (self::map(self::get()['webhooks'] ?? null) as $type => $webhook) {
            $webhooks[(string) $type] = self::map($webhook);
        }

        return $webhooks;
    }

    /**
     * The schema a `$ref` points at, or the value itself, with an `allOf`
     * merged into one object schema: the properties of every part, and
     * every part's required members.
     *
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    public static function resolve(array $value): array
    {
        $ref = $value['$ref'] ?? null;
        if (is_string($ref)) {
            if (!str_starts_with($ref, '#/components/schemas/')) {
                throw new RuntimeException(sprintf('Unsupported reference "%s".', $ref));
            }
            $value = self::schema(substr($ref, strlen('#/components/schemas/')));
        }
        if (!isset($value['allOf'])) {
            return $value;
        }

        $merged = $value;
        unset($merged['allOf']);
        $properties = self::map($merged['properties'] ?? null);
        $required = self::list($merged['required'] ?? []);
        foreach (self::list($value['allOf']) as $part) {
            $part = self::resolve(self::map($part));
            $properties = [...$properties, ...self::map($part['properties'] ?? null)];
            $required = [...$required, ...self::list($part['required'] ?? [])];
            $merged['type'] ??= $part['type'] ?? null;
        }
        $merged['properties'] = $properties;
        $merged['required'] = array_values(array_unique($required, SORT_REGULAR));

        return $merged;
    }

    /**
     * The name of the component a `$ref` points at, or null.
     *
     * @param array<array-key, mixed> $value
     */
    public static function refName(array $value): ?string
    {
        $ref = $value['$ref'] ?? null;

        return is_string($ref) && str_starts_with($ref, '#/components/schemas/') ? substr($ref, strlen('#/components/schemas/')) : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}

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

    /** @var array<string, mixed>|null */
    private static ?array $document = null;

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        if (self::$document === null) {
            $document = Yaml::parseFile(__DIR__ . '/openapi.yaml');
            if (!is_array($document)) {
                throw new RuntimeException('The pinned document is not a YAML mapping.');
            }
            self::$document = self::stringKeys($document);
        }

        return self::$document;
    }

    /**
     * Every operation, keyed by operationId.
     *
     * @return array<string, array{method: string, path: string, operation: array<string, mixed>, parameters: list<array<string, mixed>>}>
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
     * @return array<string, mixed>
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
     * @return array<string, mixed>
     */
    public static function schemas(): array
    {
        return self::map(self::map(self::get()['components'] ?? null)['schemas'] ?? null);
    }

    /**
     * The schema a `$ref` points at, or the value itself.
     *
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function resolve(array $value): array
    {
        $ref = $value['$ref'] ?? null;
        if (!is_string($ref)) {
            return $value;
        }
        if (!str_starts_with($ref, '#/components/schemas/')) {
            throw new RuntimeException(sprintf('Unsupported reference "%s".', $ref));
        }

        return self::schema(substr($ref, strlen('#/components/schemas/')));
    }

    /**
     * The name of the component a `$ref` points at, or null.
     *
     * @param array<string, mixed> $value
     */
    public static function refName(array $value): ?string
    {
        $ref = $value['$ref'] ?? null;

        return is_string($ref) && str_starts_with($ref, '#/components/schemas/') ? substr($ref, strlen('#/components/schemas/')) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        return is_array($value) ? self::stringKeys($value) : [];
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    private static function stringKeys(array $value): array
    {
        $strings = [];
        foreach ($value as $key => $member) {
            $strings[(string) $key] = $member;
        }

        return $strings;
    }
}

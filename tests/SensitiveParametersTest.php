<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Model\Entitlement;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use SensitiveParameter;
use SplFileInfo;

/**
 * A parameter that can carry a secret or a token is marked
 * #[\SensitiveParameter], so a stack trace, an error tracker or a log of an
 * exception shows a placeholder instead of the value. Parameters are found
 * by name, so a method added later is held to the same rule.
 *
 * @phpstan-import-type Token from SourceTokens
 * @phpstan-type Declaration array{name: string, line: int, fields: bool, sensitive: bool, at: int}
 */
final class SensitiveParametersTest extends TestCase
{
    private const array SENSITIVE_NAMES = [
        // Tokens and the codes that buy them.
        'token', 'accessToken', 'refreshToken', 'idToken', 'jwt', 'raw', 'code', 'codeVerifier', 'verifier',
        // Secrets.
        'clientSecret', 'secret', 'secrets', 'key',
        // Containers of the above: form fields, headers, a callback query,
        // a stored ID token, a request carrying any of them.
        'fields', 'headers', 'query', 'stored', 'request',
    ];

    /**
     * Named generically, yet carrying whatever is cached, which may be a credential.
     */
    private const array SENSITIVE_PARAMETERS = [
        [InMemoryCache::class, 'set', 'value'],
        [InMemoryCache::class, 'setMultiple', 'values'],
    ];

    /**
     * Named like a secret, yet not one: a PSR-16 cache key, a key id, the
     * name of what an entitlement grants.
     */
    private const array NOT_SENSITIVE = [
        [Entitlement::class, '__construct', 'key'],
        [InMemoryCache::class, 'get', 'key'],
        [InMemoryCache::class, 'set', 'key'],
        [InMemoryCache::class, 'delete', 'key'],
        [InMemoryCache::class, 'has', 'key'],
        [InMemoryCache::class, 'stringKey', 'key'],
        [InMemoryCache::class, 'assertKey', 'key'],
    ];

    public function testEveryParameterNamedLikeASecretOrATokenIsMarkedSensitive(): void
    {
        $checked = 0;
        foreach (self::parameters() as [$class, $method, $parameter]) {
            if (!in_array($parameter->getName(), self::SENSITIVE_NAMES, true) || in_array([$class, $method, $parameter->getName()], self::NOT_SENSITIVE, true)) {
                continue;
            }

            self::assertNotSame([], $parameter->getAttributes(SensitiveParameter::class), sprintf('%s::%s() $%s is not marked #[\SensitiveParameter].', $class, $method, $parameter->getName()));
            $checked++;
        }

        self::assertGreaterThan(0, $checked);
    }

    /**
     * The site's JSON carries members' names, addresses and codes. Every
     * parameter that holds it is marked, in a method or a closure, whatever
     * it is named: one typed Fields, the array a Fields is built from, and
     * an array handed straight to Fields::of() or Fields::ofDelivery(). The
     * source is read as tokens, as reflection does not reach closures.
     */
    public function testEveryParameterHoldingTheSitesJsonIsMarkedSensitive(): void
    {
        $unmarked = [];
        $checked = 0;
        foreach (SourceTokens::sources() as $file => $tokens) {
            $declarations = self::declarations($tokens);
            foreach ($declarations as $declaration) {
                if ($declaration['fields']) {
                    $checked++;
                    if (!$declaration['sensitive']) {
                        $unmarked[] = sprintf('%s:%d %s', $file, $declaration['line'], $declaration['name']);
                    }
                }
            }
            foreach (self::handedToFields($tokens, $declarations) as $declaration) {
                $checked++;
                if (!$declaration['sensitive']) {
                    $unmarked[] = sprintf('%s:%d %s', $file, $declaration['line'], $declaration['name']);
                }
            }
        }

        foreach ((new ReflectionClass(Fields::class))->getMethods() as $method) {
            $returnType = $method->getReturnType();
            if (!$method->isConstructor() && !($method->isStatic() && $returnType instanceof ReflectionNamedType && $returnType->getName() === 'self')) {
                continue;
            }
            foreach ($method->getParameters() as $parameter) {
                if ((string) $parameter->getType() === 'array') {
                    $checked++;
                    if ($parameter->getAttributes(SensitiveParameter::class) === []) {
                        $unmarked[] = sprintf('Fields::%s() $%s', $method->getName(), $parameter->getName());
                    }
                }
            }
        }

        self::assertSame([], $unmarked, 'Not marked #[\\SensitiveParameter].');
        self::assertGreaterThan(0, $checked);
    }

    public function testTheJsonRuleSeesClosuresAndWhatIsHandedToFields(): void
    {
        $tokens = SourceTokens::of(<<<'PHP'
            <?php
            $a = static fn(Fields $json): int => 1;
            $b = static fn(#[\SensitiveParameter] Fields $json): int => 1;
            $c = static fn(array $item): mixed => $read(Fields::of($item, $schema));
            $d = static fn(#[\SensitiveParameter] array $item): mixed => Fields::ofDelivery($item, 'x');
            function e(Fields $one, string $name, \Appsolutely\Sdk\Model\Fields $two) {}
            PHP);

        $declarations = self::declarations($tokens);
        $unmarked = array_map(
            static fn(array $declaration): string => $declaration['name'],
            array_filter([...array_filter($declarations, static fn(array $declaration): bool => $declaration['fields']), ...self::handedToFields($tokens, $declarations)], static fn(array $declaration): bool => !$declaration['sensitive']),
        );

        self::assertSame(['$json', '$one', '$two', '$item'], array_values($unmarked));
    }

    /**
     * Every parameter declared in the tokens, of a method, a function or a
     * closure: its name, its line, whether it is typed Fields and whether it
     * is marked, and where the declaration starts.
     *
     * @param list<Token> $tokens
     * @return list<Declaration>
     */
    private static function declarations(array $tokens): array
    {
        $declarations = [];
        foreach ($tokens as $index => [$id]) {
            if ($id !== T_FUNCTION && $id !== T_FN) {
                continue;
            }
            $open = $index + 1;
            while (isset($tokens[$open]) && !in_array($tokens[$open][0], ['(', ';', '{'], true)) {
                $open++;
            }
            if (($tokens[$open][0] ?? null) !== '(') {
                continue;
            }
            foreach (SourceTokens::arguments($tokens, $open) as $parameter) {
                $variable = null;
                $fields = false;
                $sensitive = false;
                foreach ($parameter as [$tokenId, $text, $line]) {
                    $variable ??= $tokenId === T_VARIABLE ? [$text, $line] : null;
                    $fields = $fields || (in_array($tokenId, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && ($text === 'Fields' || str_ends_with($text, '\\Fields')));
                    $sensitive = $sensitive || ltrim($text, '\\') === 'SensitiveParameter';
                }
                if ($variable !== null) {
                    $declarations[] = ['name' => $variable[0], 'line' => $variable[1], 'fields' => $fields, 'sensitive' => $sensitive, 'at' => $index];
                }
            }
        }

        return $declarations;
    }

    /**
     * The parameters handed, as the first argument and nothing more, to
     * Fields::of() or Fields::ofDelivery(): for each such call, the nearest
     * declaration before it of the variable passed.
     *
     * @param list<Token> $tokens
     * @param list<Declaration> $declarations
     * @return list<Declaration>
     */
    private static function handedToFields(array $tokens, array $declarations): array
    {
        $handed = [];
        foreach ($tokens as $index => [$id, $text]) {
            if ($id !== T_STRING || $text !== 'Fields' || ($tokens[$index + 1][0] ?? null) !== T_DOUBLE_COLON || !in_array($tokens[$index + 2][1] ?? null, ['of', 'ofDelivery'], true) || ($tokens[$index + 3][0] ?? null) !== '(') {
                continue;
            }
            $first = SourceTokens::arguments($tokens, $index + 3)[0];
            if (count($first) !== 1 || $first[0][0] !== T_VARIABLE) {
                continue;
            }
            $nearest = null;
            foreach ($declarations as $declaration) {
                if ($declaration['at'] < $index && $declaration['name'] === $first[0][1]) {
                    $nearest = $declaration;
                }
            }
            if ($nearest !== null) {
                $handed[] = $nearest;
            }
        }

        return $handed;
    }

    public function testEveryExceptionNamesAParameterThatExists(): void
    {
        foreach (self::NOT_SENSITIVE as [$class, $method, $name]) {
            self::assertTrue(method_exists($class, $method), sprintf('NOT_SENSITIVE names %s::%s(), which no longer exists; drop the entry.', $class, $method));
            self::assertContains($name, array_map(static fn(ReflectionParameter $parameter): string => $parameter->getName(), (new \ReflectionMethod($class, $method))->getParameters()), sprintf('NOT_SENSITIVE names %s::%s() $%s, which no longer exists; drop the entry.', $class, $method, $name));
        }
    }

    public function testParametersNamedGenericallyButCarryingTokensAreMarkedSensitive(): void
    {
        foreach (self::SENSITIVE_PARAMETERS as [$class, $method, $name]) {
            $parameter = new ReflectionParameter([$class, $method], $name);

            self::assertNotSame([], $parameter->getAttributes(SensitiveParameter::class), sprintf('%s::%s() $%s is not marked #[\SensitiveParameter].', $class, $method, $name));
        }
    }

    /**
     * @return iterable<array{string, string, ReflectionParameter}>
     */
    private static function parameters(): iterable
    {
        $root = dirname(__DIR__) . '/src';

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $class = 'Appsolutely\\Sdk\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -strlen('.php')));
            if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                foreach ($method->getParameters() as $parameter) {
                    yield [$class, $method->getName(), $parameter];
                }
            }
        }
    }
}

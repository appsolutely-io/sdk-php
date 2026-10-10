<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Cache\InMemoryCache;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionParameter;
use SensitiveParameter;
use SplFileInfo;

/**
 * A parameter that can carry a secret or a token is marked
 * #[\SensitiveParameter], so a stack trace, an error tracker or a log of an
 * exception shows a placeholder instead of the value. Parameters are found
 * by name, so a method added later is held to the same rule.
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
     * Named like a secret, yet not one: a PSR-16 cache key, a key id.
     */
    private const array NOT_SENSITIVE = [
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

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * An integrator catches what this package throws by one rule: every
 * exception class lives in the Exception namespace and implements
 * AppsolutelyException.
 */
final class ExceptionsTest extends TestCase
{
    public function testEveryExceptionClassFollowsTheOneRule(): void
    {
        $exceptions = self::exceptionClasses();

        self::assertNotSame([], $exceptions);
        foreach ($exceptions as $class) {
            self::assertStringStartsWith('Appsolutely\\Sdk\\Exception\\', $class);
            self::assertTrue(is_a($class, AppsolutelyException::class, true), $class . ' does not implement AppsolutelyException.');
        }
    }

    /**
     * One rule for a refused argument: InvalidArgumentValueException, or a
     * subclass naming what was refused (a Config value, a webhook signing
     * secret, a cache key), so one catch clause covers them all.
     */
    public function testEveryRefusedArgumentIsAnInvalidArgumentValueException(): void
    {
        foreach (self::exceptionClasses() as $class) {
            if (is_a($class, \InvalidArgumentException::class, true)) {
                self::assertTrue(is_a($class, InvalidArgumentValueException::class, true), $class . ' is not an InvalidArgumentValueException.');
            }
        }
    }

    /**
     * An imported class named like one of PHP's own reads as PHP's own
     * wherever it is thrown or caught, so no short name repeats a global
     * class or interface.
     */
    public function testNoExceptionClassShadowsAGlobalName(): void
    {
        foreach (self::exceptionClasses() as $class) {
            $short = substr($class, (int) strrpos($class, '\\') + 1);
            self::assertFalse(class_exists('\\' . $short) || interface_exists('\\' . $short), $class . ' shadows the global ' . $short . '.');
        }
    }

    /**
     * @return list<string>
     */
    private static function exceptionClasses(): array
    {
        $exceptions = [];
        foreach (SourceTokens::classNames() as $class) {
            if ((class_exists($class) || interface_exists($class)) && is_subclass_of($class, Throwable::class)) {
                $exceptions[] = $class;
            }
        }

        return $exceptions;
    }
}

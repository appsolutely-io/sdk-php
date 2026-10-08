<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
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
        $exceptions = [];
        $root = dirname(__DIR__) . '/src';

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
            $class = 'Appsolutely\\Sdk\\' . str_replace('/', '\\', $relative);
            if ((class_exists($class) || interface_exists($class)) && is_subclass_of($class, Throwable::class)) {
                $exceptions[] = $class;
            }
        }

        self::assertNotSame([], $exceptions);
        foreach ($exceptions as $class) {
            self::assertStringStartsWith('Appsolutely\\Sdk\\Exception\\', $class);
            self::assertTrue(is_a($class, AppsolutelyException::class, true), $class . ' does not implement AppsolutelyException.');
        }
    }
}

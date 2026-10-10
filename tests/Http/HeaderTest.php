<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Http;

use Appsolutely\Sdk\Http\Header;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * A header field's name is written once, in Http\Header; the code that sends
 * or reads the field names the constant, so a misspelling cannot hide in one
 * of several copies.
 */
final class HeaderTest extends TestCase
{
    public function testNoSourceFileOutsideHeaderSpellsAHeaderNameAsALiteral(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        $header = realpath($root . '/Http/Header.php');
        $names = array_values((new ReflectionClass(Header::class))->getConstants());

        $found = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getRealPath() === $header) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            foreach ($names as $name) {
                self::assertIsString($name);
                foreach (["'" . $name . "'", '"' . $name . '"'] as $literal) {
                    if (stripos($source, $literal) !== false) {
                        $found[] = substr($file->getPathname(), strlen($root) + 1) . ': ' . $literal;
                    }
                }
            }
        }

        self::assertSame([], $found);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The package is framework-free and speaks to one site: its source names no
 * Laravel (Illuminate) symbol, which only the Laravel bridge may use, and no
 * hosting-layer concept, which a site's own client never sees.
 *
 * The source tree is scanned rather than a no-dev install exercised: the
 * package requires nothing from Illuminate, so with or without dev
 * dependencies the only place such a reference could come from is src/.
 */
final class FrameworkIndependenceTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sourceFiles(): iterable
    {
        $root = dirname(__DIR__) . '/src';

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            yield substr($file->getPathname(), strlen($root) + 1) => [$file->getPathname()];
        }
    }

    #[DataProvider('sourceFiles')]
    public function testASourceFileNamesNoIlluminateSymbol(string $path): void
    {
        self::assertStringNotContainsString('Illuminate\\', (string) file_get_contents($path));
    }

    #[DataProvider('sourceFiles')]
    public function testASourceFileNamesNoHostingLayerConcept(string $path): void
    {
        // Built from pieces so this file does not carry the word it forbids.
        $word = 'ten' . 'ant';

        self::assertDoesNotMatchRegularExpression('/' . $word . '/i', (string) file_get_contents($path));
    }
}

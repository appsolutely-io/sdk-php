<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The package is framework-free and speaks for one relying party: its source
 * names no Laravel (Illuminate) symbol, which only the Laravel bridge may use,
 * and no tenant, a server-side concept a relying party never sees.
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
    public function testASourceFileNamesNoIlluminateSymbolAndNoTenant(string $path): void
    {
        $source = (string) file_get_contents($path);

        self::assertStringNotContainsString('Illuminate\\', $source);
        self::assertDoesNotMatchRegularExpression('/tenant/i', $source);
    }
}

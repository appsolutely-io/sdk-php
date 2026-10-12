<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every problem type the client branches on is one the pinned Site API
 * document names: a slug spelled differently from the site's never matches,
 * and the branch it guards (a retry, a token renewal) silently never runs.
 */
final class ProblemTypeSlugsTest extends TestCase
{
    private const string SOURCE = __DIR__ . '/../../src';
    private const string DOCUMENT = __DIR__ . '/../Contract/openapi.yaml';

    public function testEverySlugTheClientBranchesOnIsInTheDocument(): void
    {
        $document = (string) file_get_contents(self::DOCUMENT);
        $slugs = self::branchedOnSlugs();

        // Guards the scan itself: the client branches on at least these.
        self::assertContains('idempotency-key-in-use', $slugs);
        self::assertContains('invalid-token', $slugs);
        self::assertContains('validation-failed', $slugs);

        foreach ($slugs as $slug) {
            self::assertStringContainsString('https://appsolutely.io/problems/' . $slug, $document, sprintf('The client branches on the problem type "%s", which the pinned document never names.', $slug));
        }
    }

    /**
     * The slugs passed to hasType() and the ones joined to TYPE_BASE.
     *
     * @return list<string>
     */
    private static function branchedOnSlugs(): array
    {
        $slugs = [];
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SOURCE, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all("/(?:hasType\\(|TYPE_BASE \\. )'([a-z0-9-]+)'/", $source, $matches);
            array_push($slugs, ...$matches[1]);
        }

        return array_values(array_unique($slugs));
    }
}

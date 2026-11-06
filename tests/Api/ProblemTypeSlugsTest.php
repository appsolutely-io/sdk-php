<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every problem type the client branches on is one a pinned document (the
 * Site API's or the Administrator API's) names: a slug spelled differently from the site's never matches,
 * and the branch it guards (a retry, a token renewal) silently never runs.
 */
final class ProblemTypeSlugsTest extends TestCase
{
    private const string SOURCE = __DIR__ . '/../../src';
    private const array DOCUMENTS = [__DIR__ . '/../Contract/openapi.yaml', __DIR__ . '/../Contract/admin-openapi.yaml'];

    public function testEverySlugTheClientBranchesOnIsInTheDocument(): void
    {
        $named = self::documentedSlugs();
        $slugs = self::branchedOnSlugs();

        // Guards the scans themselves: the client branches on at least
        // these, and a slug is read whole, so the start of one is not one.
        self::assertContains('idempotency-key-in-use', $slugs);
        self::assertContains('invalid-token', $slugs);
        self::assertContains('validation-failed', $slugs);
        self::assertContains('idempotency-key-in-use', $named);
        self::assertNotContains('idempotency-key', $named);

        foreach ($slugs as $slug) {
            self::assertContains($slug, $named, sprintf('The client branches on the problem type "%s", which the pinned document never names.', $slug));
        }
    }

    /**
     * Every slug the pinned documents name, each read to its end.
     *
     * @return list<string>
     */
    private static function documentedSlugs(): array
    {
        $documents = implode("\n", array_map(static fn(string $document): string => (string) file_get_contents($document), self::DOCUMENTS));
        preg_match_all('~https://appsolutely\.io/problems/([a-z0-9-]+)(?![a-z0-9-])~', $documents, $matches);

        return array_values(array_unique($matches[1]));
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

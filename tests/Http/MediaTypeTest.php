<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Http;

use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Http\MediaType;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A media type the client sends or reads is written once, in
 * Http\MediaType; the code that sends or reads it names the constant.
 */
final class MediaTypeTest extends TestCase
{
    public function testNoStringLiteralSpellsAMediaType(): void
    {
        // Written in lower case there; a media type matches in any case.
        $types = array_values((new ReflectionClass(MediaType::class))->getConstants());

        $found = [];
        foreach (SourceTokens::sources() as $file => $tokens) {
            if ($file === 'Http/MediaType.php') {
                continue;
            }
            foreach ($tokens as [$id, $text, $line]) {
                if ($id === T_CONSTANT_ENCAPSED_STRING && in_array(strtolower(substr($text, 1, -1)), $types, true)) {
                    $found[] = sprintf('%s:%d %s', $file, $line, $text);
                }
            }
        }

        self::assertSame([], $found);
    }

    public function testTheSiteApiAcceptsJsonAndProblems(): void
    {
        self::assertSame('application/json, application/problem+json', SiteApi::ACCEPT);
    }
}

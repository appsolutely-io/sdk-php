<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Http;

use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Http\MediaType;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A media type the client sends or reads is written once, in
 * Http\MediaType; the code that sends or reads it names the constant.
 *
 * A literal spells a media type when it holds a `type/subtype` whose type
 * is a registered top-level one, in any case and with or without
 * parameters, so one the constants do not yet name is refused too. A
 * `name/name` of another first part (a package name) or one inside a path
 * or URL is not a media type.
 */
final class MediaTypeTest extends TestCase
{
    private const string PATTERN = '~(?<![\w.+/-])(?:application|audio|example|font|haptics|image|message|model|multipart|text|video)/[\w!#$&^.+-]+~i';

    public function testNoStringLiteralSpellsAMediaType(): void
    {
        $found = [];
        foreach (SourceTokens::sources() as $file => $tokens) {
            if ($file === 'Http/MediaType.php') {
                continue;
            }
            foreach ($tokens as [$id, $text, $line]) {
                if (in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) && preg_match(self::PATTERN, $text) === 1) {
                    $found[] = sprintf('%s:%d %s', $file, $line, $text);
                }
            }
        }

        self::assertSame([], $found);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mediaTypes(): iterable
    {
        foreach ((new ReflectionClass(MediaType::class))->getConstants() as $name => $value) {
            self::assertIsString($value);
            yield 'MediaType::' . $name => [$value];
        }
        foreach (['application/json; charset=utf-8', 'Application/Problem+JSON', 'multipart/form-data; boundary=x', 'text/plain', 'image/svg+xml', 'application/vnd.api+json'] as $value) {
            yield $value => [$value];
        }
    }

    #[DataProvider('mediaTypes')]
    public function testThePatternFindsAMediaType(string $value): void
    {
        self::assertMatchesRegularExpression(self::PATTERN, "'" . $value . "'");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notMediaTypes(): iterable
    {
        foreach (['appsolutely/sdk-php', 'Http/MediaType.php', 'https://site.test/application/json', '/text/plain', 'api/v1', 'and/or'] as $value) {
            yield $value => [$value];
        }
    }

    #[DataProvider('notMediaTypes')]
    public function testThePatternPassesWhatIsNotAMediaType(string $value): void
    {
        self::assertDoesNotMatchRegularExpression(self::PATTERN, "'" . $value . "'");
    }

    public function testTheSiteApiAcceptsJsonAndProblems(): void
    {
        self::assertSame('application/json, application/problem+json', SiteApi::ACCEPT);
    }
}

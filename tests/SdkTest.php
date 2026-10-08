<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests;

use Appsolutely\Sdk\Sdk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SdkTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, bool, string}>
     */
    public static function versions(): iterable
    {
        yield 'a release' => ['1.2.3', false, '1.2.3'];
        yield 'a release tag with a v' => ['v1.2.3', false, 'v1.2.3'];
        yield 'a pre-release' => ['2.0.0-RC1', false, '2.0.0-RC1'];
        yield 'build metadata' => ['1.2.3+build.5', false, '1.2.3+build.5'];
        yield 'a branch' => ['dev-main', false, 'dev-main'];
        // A slash is not a token character (RFC 9110 section 5.6.2).
        yield 'a branch with a slash' => ['dev-feat/client-core', false, 'dev-feat-client-core'];
        yield 'spaces and parentheses' => ['1.0.0 (beta)', false, '1.0.0--beta-'];
        yield 'a line break' => ["1.0\r\nX", false, '1.0--X'];
        // No real version: the package is not installed, or it is the root
        // package (this repository checked out), whose version Composer
        // guesses from the checkout or leaves as its placeholder.
        yield 'not installed' => [null, false, 'dev'];
        yield 'an empty version' => ['', false, 'dev'];
        yield 'Composer placeholder' => ['1.0.0+no-version-set', false, 'dev'];
        yield 'the root package' => ['1.2.3', true, 'dev'];
        yield 'the root package on a branch' => ['dev-feat/client-core', true, 'dev'];
    }

    #[DataProvider('versions')]
    public function testTheVersionIsTheInstalledOneAsAHeaderToken(?string $installed, bool $isRootPackage, string $expected): void
    {
        self::assertSame($expected, Sdk::versionFrom($installed, $isRootPackage));
    }

    /**
     * In this repository the package is the root package.
     */
    public function testTheUserAgentNamesTheSdkAndPhpVersions(): void
    {
        self::assertSame('appsolutely-sdk-php/dev PHP/' . PHP_VERSION, Sdk::userAgent());
    }
}

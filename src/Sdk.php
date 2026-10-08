<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

use Composer\InstalledVersions;

/**
 * @internal
 */
final class Sdk
{
    public const string PACKAGE = 'appsolutely/sdk-php';

    /** What Composer reports for a root package with no version to guess from. */
    private const string NO_VERSION_SET = '+no-version-set';

    /**
     * The version Composer installed, so it cannot fall behind a release the
     * way a constant kept by hand does.
     */
    public static function version(): string
    {
        return self::versionFrom(
            InstalledVersions::isInstalled(self::PACKAGE) ? InstalledVersions::getPrettyVersion(self::PACKAGE) : null,
            InstalledVersions::getRootPackage()['name'] === self::PACKAGE,
        );
    }

    /**
     * `dev` when there is no real version: the package was not installed by
     * Composer, or it is the root package (this repository checked out),
     * whose version is guessed from the checkout or is Composer's
     * placeholder. Characters a header token does not allow (RFC 9110
     * section 5.6.2), such as the slash of a branch version, become hyphens.
     */
    public static function versionFrom(?string $prettyVersion, bool $isRootPackage): string
    {
        if ($isRootPackage || $prettyVersion === null || $prettyVersion === '' || str_ends_with($prettyVersion, self::NO_VERSION_SET)) {
            return 'dev';
        }

        return (string) preg_replace('/[^A-Za-z0-9!#$%&\'*+.^_`|~-]/', '-', $prettyVersion);
    }

    public static function userAgent(): string
    {
        return sprintf('appsolutely-sdk-php/%s PHP/%s', self::version(), PHP_VERSION);
    }
}

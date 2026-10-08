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

    /**
     * The version Composer installed, so it cannot fall behind a release the
     * way a constant kept by hand does; `dev` when the package was loaded
     * some other way. Characters a header token does not allow, such as the
     * slash of a branch version, become hyphens.
     */
    public static function version(): string
    {
        $version = InstalledVersions::isInstalled(self::PACKAGE)
            ? InstalledVersions::getPrettyVersion(self::PACKAGE)
            : null;

        return $version === null || $version === ''
            ? 'dev'
            : (string) preg_replace('/[^A-Za-z0-9!#$%&\'*+.^_`|~-]/', '-', $version);
    }

    public static function userAgent(): string
    {
        return sprintf('appsolutely-sdk-php/%s PHP/%s', self::version(), PHP_VERSION);
    }
}

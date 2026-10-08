<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

/**
 * @internal
 */
final class Sdk
{
    public const string VERSION = '0.1.0';

    public static function userAgent(): string
    {
        return sprintf('appsolutely-sdk-php/%s PHP/%s', self::VERSION, PHP_VERSION);
    }
}

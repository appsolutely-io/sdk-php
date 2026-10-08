<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use RuntimeException;

/**
 * The authorization response that reached the redirect URI is not one this client asked for.
 */
final class AuthorizationResponseException extends RuntimeException implements AppsolutelyException {}

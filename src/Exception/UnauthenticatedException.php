<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * The site did not accept the credential, or none was sent (a 401);
 * `$challenge` holds the `WWW-Authenticate` header. Renew the token rather
 * than retrying it: refused credentials count against a budget of their own.
 */
final class UnauthenticatedException extends ApiException {}

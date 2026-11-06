<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

/**
 * What the call names does not exist on the site, or is not visible to the
 * credential (a 404).
 */
final class NotFoundException extends ApiException {}

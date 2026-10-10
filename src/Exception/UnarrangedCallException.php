<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use LogicException;

/**
 * A Testing\FakeClient was called for an operation it was given no answer
 * for, or at a path that is no operation of the Site API document: a
 * mistake in the test's arrangement rather than an answer of a site, so it
 * is a LogicException.
 */
final class UnarrangedCallException extends LogicException implements AppsolutelyException {}

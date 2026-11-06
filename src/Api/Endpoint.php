<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Attribute;

/**
 * Marks the one method that calls an operation of the Site API document,
 * so the contract test can hold the methods and the document to each other.
 *
 * @internal
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Endpoint
{
    public function __construct(public Operation $operation) {}
}

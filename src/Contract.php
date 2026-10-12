<?php

declare(strict_types=1);

namespace Appsolutely\Sdk;

/**
 * The Site API document this client is written and tested against.
 *
 * A site answers the operations of its own version of the document; an
 * operation the site has and this client lacks is still reachable through
 * the untyped calls of `api()->raw()`.
 */
final class Contract
{
    /**
     * The commit of the site software (aio) whose `docs/api/openapi.yaml`
     * the contract test reads. Written by `composer pin-contract`, together
     * with the copy of that document; never edit it by hand.
     */
    public const string REVISION = '6e328bc8a699df7ff91213ad188b32b91236df75';

    private function __construct() {}
}

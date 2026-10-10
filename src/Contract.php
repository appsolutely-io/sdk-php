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
    public const string REVISION = '69006fca9128cebf49027ddb47d6849931fed8e4';

    private function __construct() {}
}

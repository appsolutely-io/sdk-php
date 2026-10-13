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
    public const string REVISION = 'cdbebbb9331f5ba57ef8c1617b2208634dfd4240';

    /**
     * REVISION's release in the site software: its version tag (`v0.24.0`),
     * otherwise the version tag before it, the commits since and the full
     * commit, as `git describe` names it, or `unreleased` when no version
     * tag precedes it. Written by `composer pin-contract` with REVISION;
     * never edit it by hand.
     */
    public const string RELEASE = 'v0.24.0';

    private function __construct() {}
}

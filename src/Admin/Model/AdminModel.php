<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Model;

use Appsolutely\Sdk\Model\Fields;

/**
 * A record read from the Administrator API, by the schema of the pinned
 * Administrator API document it names; the contract test holds each to it.
 */
interface AdminModel
{
    /** The name of the document's schema. */
    public const string SCHEMA = '';

    /**
     * @internal
     */
    public static function from(Fields $json): static;
}

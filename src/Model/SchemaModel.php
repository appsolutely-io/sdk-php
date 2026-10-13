<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A model read from one named schema of the Site API document: SCHEMA names
 * the schema and from() reads an object of it. A typed method names the
 * model class alone, so the schema an answer is held to and the model that
 * reads it are one choice and cannot disagree.
 *
 * @internal
 */
interface SchemaModel
{
    /**
     * The name of the document's schema; every model names its own, which
     * the contract test holds it to.
     */
    public const string SCHEMA = '';

    /**
     * @return static
     */
    public static function from(#[\SensitiveParameter] Fields $json): self;
}

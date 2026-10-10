<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

/**
 * The media types the client sends or reads, so each is spelled in one
 * place.
 *
 * @internal
 */
final class MediaType
{
    public const string JSON = 'application/json';
    /** RFC 9457: the body of a refusal. */
    public const string PROBLEM_JSON = 'application/problem+json';
}

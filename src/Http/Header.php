<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

/**
 * The name of every HTTP header field the client sends or reads, so each is
 * spelled in one place.
 *
 * @internal
 */
final class Header
{
    public const string ACCEPT = 'Accept';
    public const string AUTHORIZATION = 'Authorization';
    public const string CACHE_CONTROL = 'Cache-Control';
    public const string CONTENT_TYPE = 'Content-Type';
    public const string IDEMPOTENCY_KEY = 'Idempotency-Key';
    public const string IDEMPOTENT_REPLAYED = 'Idempotent-Replayed';
    public const string LOCATION = 'Location';
    public const string RATE_LIMIT = 'RateLimit';
    public const string RATE_LIMIT_POLICY = 'RateLimit-Policy';
    public const string REQUEST_ID = 'X-Request-Id';
    public const string RETRY_AFTER = 'Retry-After';
    public const string USER_AGENT = 'User-Agent';
    /** Standard Webhooks writes its three header names in lower case. */
    public const string WEBHOOK_ID = 'webhook-id';
    public const string WEBHOOK_SIGNATURE = 'webhook-signature';
    public const string WEBHOOK_TIMESTAMP = 'webhook-timestamp';
    public const string WWW_AUTHENTICATE = 'WWW-Authenticate';
}

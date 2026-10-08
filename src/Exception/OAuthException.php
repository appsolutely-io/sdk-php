<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use Appsolutely\Sdk\Http\Json;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * An OAuth 2.0 error answered by the provider: the `error` code of RFC 6749
 * section 4.1.2.1 (authorization response) or 5.2 (token endpoint), RFC 6750
 * section 3 (bearer-protected resources) or RFC 7009 section 2.2.1.
 */
final class OAuthException extends RuntimeException implements AppsolutelyException
{
    public function __construct(
        public readonly string $error,
        public readonly ?string $errorDescription = null,
        public readonly ?string $errorUri = null,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($errorDescription === null ? sprintf('OAuth error "%s".', $error) : sprintf('OAuth error "%s": %s', $error, $errorDescription));
    }

    /**
     * @param array<mixed> $fields
     */
    public static function fromFields(array $fields, ?int $statusCode = null): ?self
    {
        $error = $fields['error'] ?? null;
        if (!is_string($error) || $error === '') {
            return null;
        }

        $description = $fields['error_description'] ?? null;
        $uri = $fields['error_uri'] ?? null;

        return new self($error, is_string($description) ? $description : null, is_string($uri) ? $uri : null, $statusCode);
    }

    /**
     * The error in a JSON body, or failing that in a `WWW-Authenticate:
     * Bearer` challenge, where RFC 6750 puts it for a protected resource.
     */
    public static function fromResponse(ResponseInterface $response): ?self
    {
        $status = $response->getStatusCode();
        $body = Json::decodeObject((string) $response->getBody());
        if ($body !== null && ($exception = self::fromFields($body, $status)) !== null) {
            return $exception;
        }

        $challenge = $response->getHeaderLine('WWW-Authenticate');
        if (preg_match_all('/(error|error_description|error_uri)="([^"]*)"/', $challenge, $matches, PREG_SET_ORDER) > 0) {
            $fields = [];
            foreach ($matches as $match) {
                $fields[$match[1]] = $match[2];
            }

            return self::fromFields($fields, $status);
        }

        return null;
    }
}

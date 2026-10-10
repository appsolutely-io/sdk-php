<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Exception;

use Appsolutely\Sdk\Api\RateLimit;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Http\RetryAfter;
use Appsolutely\Sdk\Support\Untrusted;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * The site refused a Site API call. The site sends a refusal as an RFC 9457
 * problem (`application/problem+json`): branch on `$type`, which names one
 * documented refusal and never changes meaning; `$title` and `$detail` are
 * prose for a person. A subclass covers each case a caller usually handles
 * on its own (validation, not found, unauthenticated, forbidden, conflict,
 * rate limited, unavailable); anything else, including a type this version
 * does not know, is this class itself.
 *
 * An error that is not a problem (a proxy's HTML page, say) still arrives
 * here, with `$isProblem` false, `$type` `about:blank` and the raw body kept.
 */
class ApiException extends RuntimeException implements AppsolutelyException
{
    /** Where the site's problem types live; a type is this followed by its slug. */
    public const string TYPE_BASE = 'https://appsolutely.io/problems/';

    /** RFC 9457 section 4.2.1: the type of a problem that adds nothing to its status. */
    public const string ABOUT_BLANK = 'about:blank';

    /**
     * @internal built by the client from the site's answer
     *
     * @param array<string, list<string>> $errors
     * @param array<array-key, mixed> $problem
     */
    final public function __construct(
        public readonly int $status,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $detail,
        public readonly ?string $instance,
        public readonly array $errors,
        public readonly array $problem,
        public readonly bool $isProblem,
        public readonly string $body,
        public readonly ?string $requestId,
        public readonly ?int $retryAfter,
        public readonly ?string $challenge,
        public readonly RateLimit $rateLimit,
    ) {
        // Everything here came from the network: kept as received on the
        // properties, made printable and short in the message.
        parent::__construct(sprintf(
            'The site answered %d %s%s%s',
            $status,
            Untrusted::text($title, Untrusted::MAX_LONG_LENGTH),
            $detail === null ? '' : ': ' . Untrusted::text($detail, Untrusted::MAX_LONG_LENGTH),
            $requestId === null ? '.' : sprintf(' (request %s).', Untrusted::text($requestId)),
        ));
    }

    /**
     * The exception for a non-2xx answer, of the subclass its status and
     * type call for. Never fails: a broken problem body becomes defaults.
     *
     * @internal
     *
     * @param string|null $requestId the X-Request-Id the request was sent with, kept when the answer names none
     */
    public static function fromResponse(ResponseInterface $response, ?string $requestId, DateTimeInterface $now): self
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $mediaType = strtolower(trim(explode(';', $response->getHeaderLine(Header::CONTENT_TYPE))[0]));
        $problem = $mediaType === 'application/problem+json' ? Json::decodeObject($body) : null;
        $members = $problem ?? [];

        $type = self::nonEmptyString($members['type'] ?? null) ?? self::ABOUT_BLANK;
        $title = self::nonEmptyString($members['title'] ?? null)
            ?? ($response->getReasonPhrase() !== '' ? $response->getReasonPhrase() : 'HTTP ' . $status);
        $retryAfter = $response->hasHeader(Header::RETRY_AFTER) ? RetryAfter::seconds($response->getHeaderLine(Header::RETRY_AFTER), $now) : null;
        $challenge = $response->getHeaderLine(Header::WWW_AUTHENTICATE);
        $answeredId = $response->getHeaderLine(Header::REQUEST_ID);

        $class = match (true) {
            $status === 422 && $type === self::TYPE_BASE . 'validation-failed' => ValidationFailedException::class,
            $status === 401 => UnauthenticatedException::class,
            $status === 403 => ForbiddenException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 429 => RateLimitedException::class,
            $status === 503 => ServiceUnavailableException::class,
            default => self::class,
        };

        return new $class(
            $status,
            $type,
            $title,
            self::nonEmptyString($members['detail'] ?? null),
            self::nonEmptyString($members['instance'] ?? null),
            self::errors($members['errors'] ?? null),
            $members,
            $problem !== null,
            $body,
            $answeredId !== '' ? $answeredId : $requestId,
            $retryAfter,
            $challenge !== '' ? $challenge : null,
            RateLimit::fromHeaders($response->getHeaderLine(Header::RATE_LIMIT_POLICY), $response->getHeaderLine(Header::RATE_LIMIT)),
        );
    }

    /**
     * Whether the problem is the site's type with this slug, such as
     * `validation-failed` or `idempotency-key-reused`.
     */
    public function hasType(string $slug): bool
    {
        return $this->type === self::TYPE_BASE . $slug;
    }

    /**
     * A member of the problem beyond the standard ones, such as
     * `required_ability` on `missing-ability`, or null when absent.
     */
    public function extension(string $name): mixed
    {
        return $this->problem[$name] ?? null;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The `errors` member as field => messages, dropping what is not.
     *
     * @return array<string, list<string>>
     */
    private static function errors(mixed $errors): array
    {
        if (!is_array($errors)) {
            return [];
        }

        $fields = [];
        foreach ($errors as $field => $messages) {
            if (!is_array($messages)) {
                continue;
            }
            $fields[(string) $field] = array_values(array_filter($messages, is_string(...)));
        }

        return $fields;
    }
}

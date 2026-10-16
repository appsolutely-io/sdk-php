<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Exception\TransportException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Http\BearerToken;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Http\MediaType;
use Appsolutely\Sdk\Http\SecureUrl;
use Appsolutely\Sdk\Support\Untrusted;
use Appsolutely\Sdk\Support\Uuid;
use JsonException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Calls to the Site API, every one carrying the credential of the client it
 * came from: the administrator token from Client::api(), the member's
 * access token from Client::forMember()->api(), or none when the Config
 * holds no administrator token.
 *
 * Paths are the ones the site's API document names, such as
 * `/api/v1/articles`, with identifiers filled in as the site gave them. A
 * success is answered as an ApiResponse; a refusal is thrown as an
 * Exception\ApiException. Reads and keyed writes are retried as the
 * Config's RetryPolicy says; other writes never are.
 */
final readonly class SiteApi
{
    public const string ACCEPT = MediaType::JSON . ', ' . MediaType::PROBLEM_JSON;

    /** The page size the site uses when none is sent, and the bounds it accepts. */
    public const int DEFAULT_LIMIT = 25;
    public const int MIN_LIMIT = 1;
    public const int MAX_LIMIT = 100;

    private string $origin;

    /**
     * @internal obtain it from Client::api() or MemberClient::api()
     */
    public function __construct(
        private HttpTransport $http,
        string $baseUrl,
        #[\SensitiveParameter]
        private ?string $token,
        private ClockInterface $clock,
        private RetryPolicy $retryPolicy,
        private LoggerInterface $logger,
        private ?BackendToken $backendToken = null,
        private ?string $pathPrefix = null,
    ) {
        $this->origin = rtrim($baseUrl, '/');
    }

    /**
     * The same calls carrying the token the site backend's machine client is
     * granted, asked for when needed and once more when the site no longer
     * accepts it.
     *
     * @internal used by Client::backend()
     */
    public function withBackendToken(BackendToken $backendToken, ?string $pathPrefix = null): self
    {
        return new self($this->http, $this->origin, null, $this->clock, $this->retryPolicy, $this->logger, $backendToken, $pathPrefix);
    }

    /**
     * The same calls carrying another bearer token.
     *
     * @internal used by Client::forMember()
     */
    public function withToken(#[\SensitiveParameter] string $token): self
    {
        if (!BearerToken::isSendable($token)) {
            throw new InvalidArgumentValueException('An access token must be a non-empty run of printable ASCII characters without spaces.');
        }

        return new self($this->http, $this->origin, $token, $this->clock, $this->retryPolicy, $this->logger);
    }

    /**
     * The same calls carrying no credential, for the operations anyone may
     * call: a token sent where none is needed only adds to what can leak.
     *
     * @internal
     */
    public function withoutToken(): self
    {
        return new self($this->http, $this->origin, null, $this->clock, $this->retryPolicy, $this->logger);
    }

    /**
     * @param array<string, string|int|bool|list<string>|null> $query a null value is left out, a boolean is sent as `true` or `false`, a list as `name[]` once per value
     * @param string|null $requestId sent as X-Request-Id; a fresh UUID when none is given
     */
    public function get(string $path, #[\SensitiveParameter] array $query = [], ?string $requestId = null): ApiResponse
    {
        return $this->call('GET', $path, $query, null, $requestId, retryable: true);
    }

    /**
     * One page of a list.
     *
     * @param array<string, string|int|bool|list<string>|null> $query the list's filters, the same for every page
     * @param string|null $cursor the previous page's nextCursor, unchanged; null for the first page
     * @return Page<array<array-key, mixed>>
     */
    public function page(string $path, #[\SensitiveParameter] array $query = [], int $limit = self::DEFAULT_LIMIT, ?string $cursor = null, ?string $requestId = null): Page
    {
        self::assertPaging($query, $limit);

        return $this->fetchPage($path, $query, $limit, $cursor, $requestId);
    }

    /**
     * Every item of a list, fetched page by page as iteration reaches it.
     *
     * @param array<string, string|int|bool|list<string>|null> $query the list's filters, sent with every page
     * @return Paginator<array<array-key, mixed>>
     */
    public function paginate(string $path, #[\SensitiveParameter] array $query = [], int $limit = self::DEFAULT_LIMIT): Paginator
    {
        self::assertPaging($query, $limit);

        return new Paginator(fn(?string $cursor): Page => $this->fetchPage($path, $query, $limit, $cursor, null));
    }

    /**
     * A POST the site runs once however often it arrives: it carries an
     * Idempotency-Key, the same on every retry of this call, and a retry
     * is answered with the first answer (`$replayed` on the result). Use it
     * for the operations the site's API document gives an Idempotency-Key.
     *
     * @param array<string, mixed> $body
     * @param string|null $idempotencyKey a key for this one operation, 1 to 255 printable ASCII characters
     *                                    (spaces included); a fresh UUID when none is given. Pass the raw
     *                                    key: the client quotes it as an RFC 9651 String. Never reuse one
     *                                    for a different request.
     */
    public function postIdempotent(string $path, #[\SensitiveParameter] array $body = [], ?string $idempotencyKey = null, ?string $requestId = null): ApiResponse
    {
        $idempotencyKey ??= Uuid::v4();
        if (preg_match('/^[\x20-\x7E]{1,255}$/D', $idempotencyKey) !== 1) {
            throw new InvalidArgumentValueException('An Idempotency-Key must be 1 to 255 printable ASCII characters.');
        }

        return $this->call('POST', $path, [], $body, $requestId, retryable: true, idempotencyKey: $idempotencyKey);
    }

    /**
     * A POST without an Idempotency-Key, never retried.
     *
     * @param array<string, mixed> $body
     */
    public function post(string $path, #[\SensitiveParameter] array $body = [], ?string $requestId = null): ApiResponse
    {
        return $this->call('POST', $path, [], $body, $requestId);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function put(string $path, #[\SensitiveParameter] array $body = [], ?string $requestId = null): ApiResponse
    {
        return $this->call('PUT', $path, [], $body, $requestId);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function patch(string $path, #[\SensitiveParameter] array $body = [], ?string $requestId = null): ApiResponse
    {
        return $this->call('PATCH', $path, [], $body, $requestId);
    }

    /**
     * @param array<string, string|int|bool|list<string>|null> $query
     */
    public function delete(string $path, #[\SensitiveParameter] array $query = [], ?string $requestId = null): ApiResponse
    {
        return $this->call('DELETE', $path, $query, null, $requestId);
    }

    /**
     * What var_dump() and print_r() show: the token is replaced.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['origin' => $this->origin, 'token' => $this->token === null ? null : '[redacted]', 'backendToken' => $this->backendToken];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('A SiteApi holds a bearer token and is not serialized; obtain it again from its Client.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('A SiteApi holds a bearer token and is not unserialized; obtain it again from its Client.');
    }

    /**
     * @param array<string, string|int|bool|list<string>|null> $query
     * @return Page<array<array-key, mixed>>
     */
    private function fetchPage(string $path, #[\SensitiveParameter] array $query, int $limit, ?string $cursor, ?string $requestId): Page
    {
        return Page::fromResponse($this->call('GET', $path, [...$query, 'limit' => $limit, 'cursor' => $cursor], null, $requestId, retryable: true), $path);
    }

    /**
     * Refuses a page size out of the site's bounds, and a query that sets
     * the page size or the cursor itself.
     *
     * @internal
     *
     * @param array<string, string|int|bool|list<string>|null> $query
     */
    public static function assertPaging(#[\SensitiveParameter] array $query, int $limit): void
    {
        if ($limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentValueException(sprintf('The page limit must be between %d and %d, got %d.', self::MIN_LIMIT, self::MAX_LIMIT, $limit));
        }

        foreach (['limit', 'cursor'] as $name) {
            if (array_key_exists($name, $query)) {
                throw new InvalidArgumentValueException(sprintf('"%s" is set by the client; pass the page size as $limit and follow the site\'s cursor rather than building one.', $name));
            }
        }
    }

    /**
     * @param array<string, string|int|bool|list<string>|null> $query
     * @param array<string, mixed>|null $body
     */
    private function call(
        string $method,
        string $path,
        #[\SensitiveParameter]
        array $query,
        #[\SensitiveParameter]
        ?array $body,
        ?string $requestId,
        bool $retryable = false,
        ?string $idempotencyKey = null,
    ): ApiResponse {
        $url = $this->url($path, $query);
        $requestId ??= Uuid::v4();
        self::assertHeaderValue('request id', $requestId);

        $headers = [Header::ACCEPT => self::ACCEPT, Header::REQUEST_ID => $requestId];
        $token = $this->backendToken?->token() ?? $this->token;
        if ($token !== null) {
            $headers[Header::AUTHORIZATION] = 'Bearer ' . $token;
        }
        $renewed = false;
        if ($idempotencyKey !== null) {
            $headers[Header::IDEMPOTENCY_KEY] = self::structuredFieldString($idempotencyKey);
        }
        $encoded = $body === null ? null : self::encode($body);

        $attempt = 0;
        for ($retry = 0; ; $retry++) {
            $context = ['method' => $method, 'path' => Untrusted::text($path, Untrusted::MAX_LONG_LENGTH), 'status' => null, 'request_id' => $requestId, 'attempt' => ++$attempt];
            try {
                $response = $this->http->json($method, $url, $headers, $encoded);
            } catch (TransportException $exception) {
                $this->logger->debug(sprintf('%s %s failed before an answer.', $method, $context['path']), $context);
                $this->waitBeforeRetrying($exception, $retryable, $retry, $context, 'a network failure', $exception->isNetworkFailure() ? $this->retryPolicy->backoff($retry + 1) : null);

                continue;
            }

            $context['status'] = $response->getStatusCode();
            $this->logger->debug(sprintf('%s %s answered %d.', $method, $context['path'], $context['status']), $context);

            if (HttpTransport::isSuccessful($response)) {
                return $this->answer($response, $method, $path, $requestId, $idempotencyKey);
            }

            $exception = ApiException::fromResponse($response, $requestId, $this->clock->now());

            // A machine token the site no longer accepts (revoked, or the
            // client's secret rotated) is asked for again, once; the call is
            // sent again with it whether or not it may be retried, since the
            // refusal came before the site acted on it.
            if ($this->backendToken !== null && $token !== null && !$renewed && $exception->status === 401 && $exception->hasType('invalid-token')) {
                $token = $this->backendToken->renew($token);
                $headers[Header::AUTHORIZATION] = 'Bearer ' . $token;
                $renewed = true;
                // Not one of the retries the policy counts.
                $retry--;

                continue;
            }

            $this->waitBeforeRetrying($exception, $retryable, $retry, $context, (string) $exception->status, $this->delay($exception, $idempotencyKey !== null, $retry + 1));
        }
    }

    /**
     * Seconds to wait before retrying after this refusal, or null when it is
     * not one a retry can change: a 429; a 502, 503 or 504, a gateway or the
     * site failing before an answer; the 409 `idempotency-key-in-use` of a
     * keyed request still running; and, for a read only, a 500. A keyed
     * write's 500 is thrown: its outcome is unknown, and only reading the
     * current state can settle it. The wait is what the site asks, or the
     * policy's backoff.
     */
    private function delay(ApiException $exception, bool $keyed, int $retry): ?float
    {
        $asked = match (true) {
            $exception->status === 429 => $exception->retryAfter ?? $exception->rateLimit->exhausted()?->reset,
            in_array($exception->status, RetryPolicy::RETRIED_STATUSES, true) => $exception->retryAfter,
            $exception->status === 500 && !$keyed => $exception->retryAfter,
            $exception->status === 409 && $keyed && $exception->hasType('idempotency-key-in-use') => $exception->retryAfter,
            default => false,
        };

        if ($asked === false) {
            return null;
        }

        return $asked === null ? $this->retryPolicy->backoff($retry) : (float) $asked;
    }

    /**
     * Waits before the next attempt, or throws the failure when the call is
     * not retried, the retries are spent, or the wait asked for is beyond
     * the policy's ceiling. Giving up on a failure that was not the site's
     * considered answer (a network failure, a 5xx, a refusal a later
     * attempt could have passed) is a warning; any other refusal is the
     * caller's to report.
     *
     * @param array{method: string, path: string, status: int|null, request_id: string, attempt: int} $context
     */
    private function waitBeforeRetrying(TransportException|ApiException $failure, bool $retryable, int $retry, array $context, string $reason, ?float $delay): void
    {
        if (!$retryable || $delay === null || $retry >= $this->retryPolicy->maxRetries || $delay > $this->retryPolicy->maxDelay) {
            if ($delay !== null || $failure instanceof TransportException || $failure->status >= 500) {
                $this->logger->warning(sprintf('%s %s failed after %d attempt(s): %s.', $context['method'], $context['path'], $context['attempt'], $reason), $context);
            }

            throw $failure;
        }

        $this->logger->info(sprintf(
            'Retrying %s %s after %s, in %.1f seconds (retry %d of %d).',
            $context['method'],
            $context['path'],
            $reason,
            $delay,
            $retry + 1,
            $this->retryPolicy->maxRetries,
        ));
        $this->retryPolicy->sleeper->sleep($delay);
    }

    private function answer(ResponseInterface $response, string $method, string $path, string $requestId, ?string $idempotencyKey): ApiResponse
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = null;
        if ($body !== '') {
            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $decoded = null;
            }
            if (!is_array($decoded)) {
                throw new UnexpectedResponseException(sprintf('%s %s answered %d with a body that is not a JSON object or list.', $method, Untrusted::text($path, Untrusted::MAX_LONG_LENGTH), $status), $status);
            }
            $data = $decoded;
        }

        $answeredId = $response->getHeaderLine(Header::REQUEST_ID);
        $location = $response->getHeaderLine(Header::LOCATION);

        return new ApiResponse(
            $status,
            $data,
            $answeredId !== '' ? $answeredId : $requestId,
            RateLimit::fromHeaders($response->getHeaderLine(Header::RATE_LIMIT_POLICY), $response->getHeaderLine(Header::RATE_LIMIT)),
            strcasecmp($response->getHeaderLine(Header::IDEMPOTENT_REPLAYED), 'true') === 0,
            $idempotencyKey,
            $location !== '' ? $location : null,
            $response,
        );
    }

    /**
     * The origin joined to an absolute path. A path that could reach another
     * host (`//host`, a scheme) or that URL parsers read differently is
     * refused: the request carries the token.
     *
     * @param array<string, string|int|bool|list<string>|null> $query
     */
    private function url(string $path, #[\SensitiveParameter] array $query): string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '?') || str_contains($path, '#') || !SecureUrl::isUnambiguous($this->origin . $path)) {
            throw new InvalidArgumentValueException(sprintf('A Site API path is absolute, without a query, a fragment, whitespace or a backslash, such as "/api/v1/articles"; got "%s".', Untrusted::text($path, Untrusted::MAX_LONG_LENGTH)));
        }

        // A client held to one API sends nothing outside it. A dot segment,
        // as written, percent-encoded or carrying a `;` parameter, would climb
        // out of it once the server resolved it, and so would an encoded
        // slash or backslash a server decodes before routing.
        if ($this->pathPrefix !== null && (!str_starts_with($path, $this->pathPrefix) || preg_match('~(^|/)(\.|%2e){1,2}(;[^/]*)?(/|$)|%2f|%5c~i', $path) === 1)) {
            throw new InvalidArgumentValueException(sprintf('This client calls only paths under "%s"; got "%s".', $this->pathPrefix, Untrusted::text($path, Untrusted::MAX_LONG_LENGTH)));
        }

        $pairs = [];
        foreach ($query as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $member) {
                if ($member !== null) {
                    $pairs[] = rawurlencode(is_array($value) ? $name . '[]' : $name) . '=' . rawurlencode(is_bool($member) ? ($member ? 'true' : 'false') : (string) $member);
                }
            }
        }

        return $this->origin . $path . ($pairs === [] ? '' : '?' . implode('&', $pairs));
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function encode(#[\SensitiveParameter] array $body): string
    {
        if ($body === []) {
            return '{}';
        }

        try {
            return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new InvalidArgumentValueException('The request body cannot be encoded as JSON: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * The key as an RFC 9651 String (section 4.1.6), the form the IETF
     * Idempotency-Key draft defines: quoted, with `\` and `"` escaped, so
     * a key holding a space, a colon or a quote arrives as it was given
     * rather than as a bare token the site would refuse.
     */
    private static function structuredFieldString(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }

    /**
     * 1 to 255 visible ASCII characters, a safe rule for any header value.
     */
    private static function assertHeaderValue(string $name, string $value): void
    {
        if (preg_match('/^[\x21-\x7E]{1,255}$/D', $value) !== 1) {
            throw new InvalidArgumentValueException(sprintf('The %s must be 1 to 255 printable ASCII characters without spaces.', $name));
        }
    }
}

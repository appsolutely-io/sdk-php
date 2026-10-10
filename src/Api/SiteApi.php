<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Http\BearerToken;
use Appsolutely\Sdk\Http\HttpTransport;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Http\SecureUrl;
use Appsolutely\Sdk\Support\Untrusted;
use Appsolutely\Sdk\Support\Uuid;
use JsonException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Calls to the Site API, every one carrying the credential of the client it
 * came from: the administrator token from Client::api(), the member's
 * access token from Client::forMember()->api(), or none when the Config
 * holds no administrator token.
 *
 * Paths are the ones the site's API document names, such as
 * `/api/v1/articles`, with identifiers filled in as the site gave them. A
 * success is answered as an ApiResponse; a refusal is thrown as an
 * Exception\ApiException.
 */
final readonly class SiteApi
{
    public const string ACCEPT = 'application/json, application/problem+json';

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
    ) {
        $this->origin = rtrim($baseUrl, '/');
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

        return new self($this->http, $this->origin, $token, $this->clock);
    }

    /**
     * @param array<string, string|int|bool|null> $query a null value is left out, a boolean is sent as `true` or `false`
     * @param string|null $requestId sent as X-Request-Id; a fresh UUID when none is given
     */
    public function get(string $path, #[\SensitiveParameter] array $query = [], ?string $requestId = null): ApiResponse
    {
        return $this->call('GET', $path, $query, null, $requestId);
    }

    /**
     * One page of a list.
     *
     * @param array<string, string|int|bool|null> $query the list's filters, the same for every page
     * @param string|null $cursor the previous page's nextCursor, unchanged; null for the first page
     * @return Page<array<string, mixed>>
     */
    public function page(string $path, #[\SensitiveParameter] array $query = [], int $limit = self::DEFAULT_LIMIT, ?string $cursor = null, ?string $requestId = null): Page
    {
        self::assertPaging($query, $limit);

        return $this->fetchPage($path, $query, $limit, $cursor, $requestId);
    }

    /**
     * Every item of a list, fetched page by page as iteration reaches it.
     *
     * @param array<string, string|int|bool|null> $query the list's filters, sent with every page
     * @return Paginator<array<string, mixed>>
     */
    public function paginate(string $path, #[\SensitiveParameter] array $query = [], int $limit = self::DEFAULT_LIMIT): Paginator
    {
        self::assertPaging($query, $limit);

        return new Paginator(fn(?string $cursor): Page => $this->fetchPage($path, $query, $limit, $cursor, null));
    }

    /**
     * A POST without an Idempotency-Key.
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
     * @param array<string, string|int|bool|null> $query
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
        return ['origin' => $this->origin, 'token' => $this->token === null ? null : '[redacted]'];
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
     * @param array<string, string|int|bool|null> $query
     * @return Page<array<string, mixed>>
     */
    private function fetchPage(string $path, #[\SensitiveParameter] array $query, int $limit, ?string $cursor, ?string $requestId): Page
    {
        $response = $this->call('GET', $path, [...$query, 'limit' => $limit, 'cursor' => $cursor], null, $requestId);
        $data = $response->data;
        $items = is_array($data) && !array_is_list($data) ? ($data['data'] ?? null) : null;
        $next = is_array($data) ? ($data['next_cursor'] ?? null) : null;

        if (!is_array($items) || !array_is_list($items) || ($next !== null && !is_string($next))) {
            throw new UnexpectedResponseException(sprintf('GET %s did not answer a page of the form {data, next_cursor}.', Untrusted::text($path, Untrusted::MAX_LONG_LENGTH)), $response->status);
        }

        $objects = [];
        foreach ($items as $item) {
            if (!is_array($item) || ($item !== [] && array_is_list($item))) {
                throw new UnexpectedResponseException(sprintf('GET %s answered a page whose items are not all objects.', Untrusted::text($path, Untrusted::MAX_LONG_LENGTH)), $response->status);
            }
            $objects[] = Json::stringKeys($item);
        }

        return new Page($objects, $next === '' ? null : $next, $response);
    }

    /**
     * @param array<string, string|int|bool|null> $query
     */
    private static function assertPaging(#[\SensitiveParameter] array $query, int $limit): void
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
     * @param array<string, string|int|bool|null> $query
     * @param array<string, mixed>|null $body
     */
    private function call(string $method, string $path, #[\SensitiveParameter] array $query, #[\SensitiveParameter] ?array $body, ?string $requestId): ApiResponse
    {
        $url = $this->url($path, $query);
        $requestId ??= Uuid::v4();
        self::assertHeaderValue('request id', $requestId);

        $headers = ['Accept' => self::ACCEPT, 'X-Request-Id' => $requestId];
        if ($this->token !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = $this->http->json($method, $url, $headers, $body === null ? null : self::encode($body));

        if (!HttpTransport::isSuccessful($response)) {
            throw ApiException::fromResponse($response, $requestId, $this->clock->now());
        }

        return $this->answer($response, $method, $path, $requestId, null);
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
            $data = array_is_list($decoded) ? $decoded : Json::stringKeys($decoded);
        }

        $answeredId = $response->getHeaderLine('X-Request-Id');
        $location = $response->getHeaderLine('Location');

        return new ApiResponse(
            $status,
            $data,
            $answeredId !== '' ? $answeredId : $requestId,
            RateLimit::fromHeaders($response->getHeaderLine('RateLimit-Policy'), $response->getHeaderLine('RateLimit')),
            strcasecmp($response->getHeaderLine('Idempotent-Replayed'), 'true') === 0,
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
     * @param array<string, string|int|bool|null> $query
     */
    private function url(string $path, #[\SensitiveParameter] array $query): string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '?') || str_contains($path, '#') || !SecureUrl::isUnambiguous($this->origin . $path)) {
            throw new InvalidArgumentValueException(sprintf('A Site API path is absolute, without a query, a fragment, whitespace or a backslash, such as "/api/v1/articles"; got "%s".', Untrusted::text($path, Untrusted::MAX_LONG_LENGTH)));
        }

        $parameters = [];
        foreach ($query as $name => $value) {
            if ($value !== null) {
                $parameters[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            }
        }

        return $this->origin . $path . ($parameters === [] ? '' : '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
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
     * 1 to 255 visible ASCII characters, the site's rule for a key and a
     * safe one for any header value.
     */
    private static function assertHeaderValue(string $name, string $value): void
    {
        if (preg_match('/^[\x21-\x7E]{1,255}$/D', $value) !== 1) {
            throw new InvalidArgumentValueException(sprintf('The %s must be 1 to 255 printable ASCII characters without spaces.', $name));
        }
    }
}

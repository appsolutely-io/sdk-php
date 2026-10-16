<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Testing;

use Appsolutely\Sdk\Admin\AdminClient;
use Appsolutely\Sdk\Admin\AdminConfig;
use Appsolutely\Sdk\Admin\AdminOperation;
use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\UnarrangedCallException;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Http\MediaType;
use Appsolutely\Sdk\Http\StructuredFieldList;
use Http\Discovery\Psr17FactoryDiscovery;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The Administrator API without a site, for an integrator's own tests:
 * arrange the answer to each operation, run the code under test against
 * client(), then read the calls it made.
 *
 *     $fake = (new FakeAdminClient())
 *         ->answer(AdminOperation::GetArticle, ['id' => 'a-1', ...], etag: '"v1"')
 *         ->refuse(AdminOperation::UpdateArticle, 'precondition-failed', 412, 'The record changed since it was read.');
 *     $service = new MyService($fake->client());
 *     ...
 *     $fake->lastCall(AdminOperation::UpdateArticle)->ifMatch;
 *
 * client() is a real AdminClient whose HTTP client answers in memory: its
 * token is granted by an in-memory issuer, and every arranged answer is
 * read by the same code a site's answer is, so a body that breaks the
 * document fails the same way and a problem becomes the same exception.
 * Calls are not retried. A test helper: not for production code.
 */
final class FakeAdminClient
{
    public const string BASE_URL = 'https://site.test';

    public const string ACCESS_TOKEN = 'fake-administrator-credential-token';

    /** The members RFC 9457 defines, which an extension member may not reuse. */
    private const array STANDARD_MEMBERS = ['type', 'title', 'status', 'detail', 'instance'];

    private readonly AdminClient $client;

    private readonly ResponseFactoryInterface $responses;

    private readonly StreamFactoryInterface $streams;

    /** @var array<string, list<array{status: int, body: array<mixed>|null, headers: array<string, string>, problem: bool}>> by operationId */
    private array $answers = [];

    /** @var list<RecordedAdminCall> */
    private array $calls = [];

    public function __construct(?ClockInterface $clock = null)
    {
        $this->responses = Psr17FactoryDiscovery::findResponseFactory();
        $this->streams = Psr17FactoryDiscovery::findStreamFactory();
        $scopes = array_values(array_unique(array_map(static fn(AdminOperation $operation): string => $operation->scope(), AdminOperation::cases())));
        $this->client = new AdminClient(new AdminConfig(
            baseUrl: self::BASE_URL,
            issuer: self::BASE_URL,
            clientId: 'fake-administrator-credential',
            clientSecret: 'fake-administrator-credential-secret',
            scopes: $scopes,
            httpClient: new FakeSite($this->handle(...)),
            requestFactory: Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: $this->streams,
            clock: $clock,
            retryPolicy: RetryPolicy::none(),
        ));
    }

    /**
     * The client to hand to the code under test, holding a credential with
     * the scope of every operation AdminOperation names.
     */
    public function client(): AdminClient
    {
        return $this->client;
    }

    /**
     * Arranges the next answer to an operation. Answers to one operation are
     * given in the order arranged, and the last one keeps answering.
     *
     * @param array<mixed>|null $body the JSON the site would answer, as the document shapes it (a list is `{data, next_cursor}`)
     * @param int|null $status the operation's success status when left out
     * @param string|null $etag the version a read or an update answers in its ETag
     * @param array<string, string> $headers such as `Idempotent-Replayed: true` for a replayed write
     */
    public function answer(AdminOperation $operation, ?array $body = null, ?int $status = null, ?string $etag = null, #[\SensitiveParameter] array $headers = []): self
    {
        $status ??= $operation->successStatus();
        if ($status < 200 || $status > 299) {
            throw new LogicException(sprintf('An answer is a success; arrange a %d with refuse().', $status));
        }
        if ($etag !== null) {
            $headers[Header::ETAG] = $etag;
        }
        $this->answers[$operation->value][] = ['status' => $status, 'body' => $body, 'headers' => $headers, 'problem' => false];

        return $this;
    }

    /**
     * Arranges one page of a list.
     *
     * @param list<array<mixed>> $items
     */
    public function answerPage(AdminOperation $operation, array $items, ?string $nextCursor = null): self
    {
        if (!$operation->isList()) {
            throw new LogicException(sprintf('%s is not a list.', $operation->value));
        }

        return $this->answer($operation, $nextCursor === null ? ['data' => $items] : ['data' => $items, 'next_cursor' => $nextCursor]);
    }

    /**
     * Arranges a refusal as the site answers one: an RFC 9457 problem
     * document of the type named (`precondition-failed`, `insufficient-scope`).
     *
     * @param array<string, mixed> $members extension members, such as `errors` or `required`
     * @param array<string, string> $headers
     */
    public function refuse(AdminOperation $operation, string $type, int $status, string $title, ?string $detail = null, array $members = [], #[\SensitiveParameter] array $headers = []): self
    {
        if ($status < 400 || $status > 599) {
            throw new LogicException(sprintf('A refusal is a 4xx or 5xx; arrange a %d with answer().', $status));
        }
        foreach (array_keys($members) as $member) {
            if (in_array($member, self::STANDARD_MEMBERS, true)) {
                throw new LogicException(sprintf('"%s" is a member every problem has; an extension member names something else.', $member));
            }
        }
        $problem = ['type' => ApiException::TYPE_BASE . $type, 'title' => $title, 'status' => $status];
        if ($detail !== null && $detail !== '') {
            $problem['detail'] = $detail;
        }
        $this->answers[$operation->value][] = ['status' => $status, 'body' => [...$problem, ...$members], 'headers' => $headers, 'problem' => true];

        return $this;
    }

    /**
     * Every call received, in order, or those to one operation.
     *
     * @return list<RecordedAdminCall>
     */
    public function calls(?AdminOperation $operation = null): array
    {
        return $operation === null
            ? $this->calls
            : array_values(array_filter($this->calls, static fn(RecordedAdminCall $call): bool => $call->operation === $operation));
    }

    public function lastCall(AdminOperation $operation): RecordedAdminCall
    {
        $calls = $this->calls($operation);
        if ($calls === []) {
            throw new LogicException(sprintf('%s was not called.', $operation->value));
        }

        return $calls[count($calls) - 1];
    }

    private function handle(#[\SensitiveParameter] RequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        if ($method === 'GET' && $path === '/.well-known/openid-configuration') {
            return $this->json([
                'issuer' => self::BASE_URL,
                'authorization_endpoint' => self::BASE_URL . '/oauth/authorize',
                'token_endpoint' => self::BASE_URL . '/oauth/token',
                'jwks_uri' => self::BASE_URL . '/oauth/jwks.json',
                'response_types_supported' => ['code'],
                'subject_types_supported' => ['public'],
                'id_token_signing_alg_values_supported' => ['RS256'],
                'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            ]);
        }
        if ($method === 'POST' && $path === '/oauth/token') {
            return $this->json(['access_token' => self::ACCESS_TOKEN, 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        [$operation, $parameters] = self::match($method, $path) ?? throw new UnarrangedCallException(sprintf(
            '%s %s is not an operation AdminOperation names.',
            $method,
            $path,
        ));

        parse_str($request->getUri()->getQuery(), $query);
        $body = (string) $request->getBody();
        $decoded = $body === '' ? null : json_decode($body, true);
        $authorization = $request->getHeaderLine(Header::AUTHORIZATION);
        $requestId = $request->getHeaderLine(Header::REQUEST_ID);
        $ifMatch = $request->getHeaderLine(Header::IF_MATCH);

        $this->calls[] = new RecordedAdminCall(
            $operation,
            $parameters,
            $query,
            is_array($decoded) ? $decoded : null,
            self::idempotencyKey($request->getHeaderLine(Header::IDEMPOTENCY_KEY)),
            $ifMatch !== '' ? $ifMatch : null,
            $requestId,
            str_starts_with($authorization, 'Bearer ') ? substr($authorization, strlen('Bearer ')) : null,
        );

        $queue = $this->answers[$operation->value] ?? [];
        if ($queue === []) {
            throw new UnarrangedCallException(sprintf('%s was called with no answer arranged; arrange one with answer() or refuse().', $operation->value));
        }
        $answer = count($queue) > 1 ? array_shift($queue) : $queue[0];
        $this->answers[$operation->value] = $queue;

        $response = $this->responses->createResponse($answer['status'])->withHeader(Header::REQUEST_ID, $requestId);
        if ($answer['status'] === 401) {
            $response = $response->withHeader(Header::WWW_AUTHENTICATE, 'Bearer error="invalid_token"');
        }
        $required = $answer['problem'] ? (($answer['body'] ?? [])['required'] ?? null) : null;
        if ($answer['status'] === 403 && is_string($required)) {
            $response = $response->withHeader(Header::WWW_AUTHENTICATE, sprintf('Bearer error="insufficient_scope", scope="%s"', $required));
        }
        foreach ($answer['headers'] as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        if ($answer['body'] !== null) {
            $response = $response
                ->withHeader(Header::CONTENT_TYPE, $answer['problem'] ? MediaType::PROBLEM_JSON : MediaType::JSON)
                ->withBody($this->streams->createStream(json_encode($answer['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body): ResponseInterface
    {
        return $this->responses->createResponse(200)
            ->withHeader(Header::CONTENT_TYPE, MediaType::JSON)
            ->withBody($this->streams->createStream(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }

    private static function idempotencyKey(string $field): ?string
    {
        if ($field === '') {
            return null;
        }
        $members = StructuredFieldList::parse($field);

        return $members !== null && count($members) === 1 && $members[0][1] === [] ? $members[0][0] : $field;
    }

    /**
     * @return array{AdminOperation, array<string, string>}|null
     */
    private static function match(string $method, string $path): ?array
    {
        foreach (AdminOperation::cases() as $operation) {
            if ($operation->method() !== $method) {
                continue;
            }
            $pattern = '#^' . preg_replace('/\\\\\{([a-z_]+)\\\\\}/', '(?P<$1>[^/]+)', preg_quote($operation->template(), '#')) . '$#D';
            if (preg_match($pattern, $path, $match) === 1) {
                $parameters = [];
                foreach ($match as $name => $value) {
                    if (is_string($name)) {
                        $parameters[$name] = rawurldecode($value);
                    }
                }

                return [$operation, $parameters];
            }
        }

        return null;
    }
}

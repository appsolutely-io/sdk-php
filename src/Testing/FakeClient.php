<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Testing;

use Appsolutely\Sdk\Api\AdministratorApi;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\UnarrangedCallException;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Http\MediaType;
use Appsolutely\Sdk\MemberClient;
use Http\Discovery\Psr17FactoryDiscovery;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The Site API without a site, for an integrator's own tests: arrange the
 * answer to each operation, run the code under test against client(), then
 * read the calls it made.
 *
 *     $fake = (new FakeClient())
 *         ->answer(Operation::GetArticle, ['id' => 'a-1', ...])
 *         ->refuse(Operation::CreateArticle, 'validation-failed', 422, 'The request did not pass validation.', members: ['errors' => [...]]);
 *     $service = new MyService($fake->client());
 *     ...
 *     $fake->lastCall(Operation::CreateArticle)->body;
 *
 * client() is a real Client whose HTTP client answers in memory, so every
 * answer is read by the same code a site's answer is: the arranged body
 * goes through the same models, an arranged problem becomes the same
 * exception, and an answer that breaks the document fails the same way.
 * Calls are not retried, so a refusal is thrown at once. Sign-in through
 * oidc() is not faked. A test helper: not for production code.
 */
final class FakeClient
{
    public const string BASE_URL = 'https://site.test';
    public const string ADMINISTRATOR_TOKEN = 'fake-administrator-token';
    public const string MEMBER_TOKEN = 'fake-member-token';

    /** The members RFC 9457 defines, which an extension member may not reuse. */
    private const array STANDARD_MEMBERS = ['type', 'title', 'status', 'detail', 'instance'];

    private readonly Client $client;

    private readonly ResponseFactoryInterface $responses;

    private readonly StreamFactoryInterface $streams;

    /** @var array<string, list<array{status: int, body: array<mixed>|null, headers: array<string, string>, problem: bool}>> by operationId */
    private array $answers = [];

    /** @var list<RecordedCall> */
    private array $calls = [];

    public function __construct(?ClockInterface $clock = null)
    {
        $this->responses = Psr17FactoryDiscovery::findResponseFactory();
        $this->streams = Psr17FactoryDiscovery::findStreamFactory();
        $this->client = new Client(new Config(
            baseUrl: self::BASE_URL,
            issuer: self::BASE_URL,
            clientId: 'fake-client',
            clientSecret: 'fake-client-secret',
            apiToken: self::ADMINISTRATOR_TOKEN,
            httpClient: new FakeSite($this->handle(...)),
            requestFactory: Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: $this->streams,
            clock: $clock,
            retryPolicy: RetryPolicy::none(),
        ));
    }

    /**
     * The client to hand to the code under test, holding the administrator
     * token ADMINISTRATOR_TOKEN.
     */
    public function client(): Client
    {
        return $this->client;
    }

    public function api(): AdministratorApi
    {
        return $this->client->api();
    }

    public function forMember(#[\SensitiveParameter] string $accessToken = self::MEMBER_TOKEN): MemberClient
    {
        return $this->client->forMember($accessToken);
    }

    /**
     * Arranges the next answer to an operation. Answers to one operation
     * are given in the order arranged, and the last one keeps answering.
     *
     * @param array<mixed>|null $body the JSON the site would answer, as the document shapes it (a list is `{data, next_cursor}`)
     * @param int|null $status the operation's success status when left out
     * @param array<string, string> $headers such as `Idempotent-Replayed: true` for a replayed write
     */
    public function answer(Operation $operation, ?array $body = null, ?int $status = null, #[\SensitiveParameter] array $headers = []): self
    {
        $status ??= $operation->successStatus();
        if ($status < 200 || $status > 299) {
            throw new LogicException(sprintf('An answer is a success; arrange a %d with refuse().', $status));
        }
        $this->answers[$operation->value][] = ['status' => $status, 'body' => $body, 'headers' => $headers, 'problem' => false];

        return $this;
    }

    /**
     * Arranges the next answer to a list: one page of items.
     *
     * @param list<array<string, mixed>> $items
     * @param string|null $nextCursor the cursor of the next page; null on the last
     */
    public function answerPage(Operation $operation, array $items, ?string $nextCursor = null): self
    {
        return $this->answer($operation, $nextCursor === null ? ['data' => $items] : ['data' => $items, 'next_cursor' => $nextCursor]);
    }

    /**
     * Arranges the next answer to an operation as a refusal: the RFC 9457
     * problem the site would send. A 401 carries the bearer challenge the
     * site sends unless `$headers` gives another.
     *
     * @param string $type the problem type, such as `validation-failed`, or a full type URI
     * @param string $title the problem's short summary, such as `The request did not pass validation.`
     * @param array<string, mixed> $members the problem's extension members, such as `errors`; never a standard member
     * @param array<string, string> $headers such as `Retry-After`
     */
    public function refuse(Operation $operation, string $type, int $status, string $title, ?string $detail = null, array $members = [], #[\SensitiveParameter] array $headers = []): self
    {
        if ($status < 400 || $status > 599) {
            throw new LogicException(sprintf('A refusal is a 4xx or 5xx, not %d.', $status));
        }
        // The site refuses to build a problem whose extensions reuse these
        // names, so a reader of `status` always gets the status.
        $clash = array_intersect(array_keys($members), self::STANDARD_MEMBERS);
        if ($clash !== []) {
            throw new LogicException(sprintf('A problem\'s extension members may not reuse a standard member: %s.', implode(', ', $clash)));
        }
        $type = str_contains($type, ':') ? $type : ApiException::TYPE_BASE . $type;
        // The site leaves out a detail that is empty, as it does a null one.
        $problem = ['type' => $type, 'title' => $title, 'status' => $status, ...($detail === null || $detail === '' ? [] : ['detail' => $detail]), ...$members];
        $this->answers[$operation->value][] = ['status' => $status, 'body' => $problem, 'headers' => $headers, 'problem' => true];

        return $this;
    }

    /**
     * The calls received, in order; only those of one operation when named.
     *
     * @return list<RecordedCall>
     */
    public function calls(?Operation $operation = null): array
    {
        return $operation === null
            ? $this->calls
            : array_values(array_filter($this->calls, static fn(RecordedCall $call): bool => $call->operation === $operation));
    }

    public function lastCall(Operation $operation): RecordedCall
    {
        $calls = $this->calls($operation);
        if ($calls === []) {
            throw new LogicException(sprintf('%s was not called.', $operation->value));
        }

        return $calls[count($calls) - 1];
    }

    private function handle(#[\SensitiveParameter] RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        [$operation, $parameters] = self::match($request->getMethod(), $path) ?? throw new UnarrangedCallException(sprintf(
            '%s %s is not an operation of the Site API document this client is pinned to.',
            $request->getMethod(),
            $path,
        ));

        parse_str($request->getUri()->getQuery(), $query);
        $body = (string) $request->getBody();
        $decoded = $body === '' ? null : json_decode($body, true);
        $authorization = $request->getHeaderLine(Header::AUTHORIZATION);
        $requestId = $request->getHeaderLine(Header::REQUEST_ID);
        $key = $request->getHeaderLine(Header::IDEMPOTENCY_KEY);

        $this->calls[] = new RecordedCall(
            $operation,
            $parameters,
            $query,
            is_array($decoded) ? $decoded : null,
            $key === '' ? null : $key,
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
        // The site challenges every 401: plainly when no token was sent, as
        // an invalid token when one was.
        if ($answer['status'] === 401) {
            $response = $response->withHeader(Header::WWW_AUTHENTICATE, $authorization === '' ? 'Bearer' : 'Bearer error="invalid_token"');
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
     * The operation a request's method and path name, and the decoded
     * values of its placeholders.
     *
     * @return array{Operation, array<string, string>}|null
     */
    private static function match(string $method, string $path): ?array
    {
        foreach (Operation::cases() as $operation) {
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

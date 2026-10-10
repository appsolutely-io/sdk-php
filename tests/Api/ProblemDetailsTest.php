<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\AppsolutelyException;
use Appsolutely\Sdk\Exception\ConflictException;
use Appsolutely\Sdk\Exception\ForbiddenException;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\RateLimitedException;
use Appsolutely\Sdk\Exception\ServiceUnavailableException;
use Appsolutely\Sdk\Exception\UnauthenticatedException;
use Appsolutely\Sdk\Exception\ValidationFailedException;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * A refusal from the site is an RFC 9457 problem; each becomes an
 * ApiException, of a subclass for the cases a caller branches on.
 */
final class ProblemDetailsTest extends TestCase
{
    private const string NOW = '2026-10-08T12:00:00+00:00';

    public function testAValidationProblemCarriesEveryMemberAndTheRequestId(): void
    {
        $exception = self::exception(self::problem(422, [
            'type' => 'https://appsolutely.io/problems/validation-failed',
            'title' => 'The request did not pass validation.',
            'status' => 422,
            'detail' => 'The title field is required.',
            'instance' => '/api/v1/articles',
            'errors' => ['title' => ['The title field is required.'], 'body' => ['Too long.', 'Not HTML.']],
        ], ['X-Request-Id' => 'req-from-site']), 'req-sent');

        self::assertInstanceOf(ValidationFailedException::class, $exception);
        self::assertInstanceOf(AppsolutelyException::class, $exception);
        self::assertSame(422, $exception->status);
        self::assertSame('https://appsolutely.io/problems/validation-failed', $exception->type);
        self::assertTrue($exception->hasType('validation-failed'));
        self::assertSame('The request did not pass validation.', $exception->title);
        self::assertSame('The title field is required.', $exception->detail);
        self::assertSame('/api/v1/articles', $exception->instance);
        self::assertSame(['title' => ['The title field is required.'], 'body' => ['Too long.', 'Not HTML.']], $exception->errors);
        self::assertSame('req-from-site', $exception->requestId);
        self::assertTrue($exception->isProblem);
        self::assertStringContainsString('422', $exception->getMessage());
        self::assertStringContainsString('The title field is required.', $exception->getMessage());
        self::assertStringContainsString('req-from-site', $exception->getMessage());
    }

    public function testTheRequestIdSentIsKeptWhenTheAnswerNamesNone(): void
    {
        self::assertSame('req-sent', self::exception(self::problem(404, ['type' => 'https://appsolutely.io/problems/not-found', 'title' => 'Not found.', 'status' => 404]), 'req-sent')->requestId);
    }

    public function testAnUnknownExtensionStaysReadable(): void
    {
        $exception = self::exception(self::problem(403, [
            'type' => 'https://appsolutely.io/problems/missing-ability',
            'title' => 'The token is missing an ability this endpoint requires.',
            'status' => 403,
            'required_ability' => 'articles:write',
            'granted' => ['articles:read'],
        ]));

        self::assertSame('articles:write', $exception->extension('required_ability'));
        self::assertSame(['articles:read'], $exception->extension('granted'));
        self::assertNull($exception->extension('absent'));
        self::assertSame('articles:write', $exception->problem['required_ability']);
    }

    /**
     * @return iterable<string, array{int, string, class-string<ApiException>}>
     */
    public static function cases(): iterable
    {
        yield 'validation' => [422, 'validation-failed', ValidationFailedException::class];
        // Other 422s are refusals of their own, not field errors to show.
        yield 'a reused idempotency key' => [422, 'idempotency-key-reused', ApiException::class];
        yield 'an invalid cursor' => [422, 'invalid-cursor', ApiException::class];
        yield 'not found' => [404, 'not-found', NotFoundException::class];
        yield 'unauthenticated' => [401, 'unauthenticated', UnauthenticatedException::class];
        yield 'forbidden' => [403, 'forbidden', ForbiddenException::class];
        yield 'missing ability' => [403, 'missing-ability', ForbiddenException::class];
        yield 'conflict' => [409, 'subscription-inactive', ConflictException::class];
        yield 'request in flight' => [409, 'idempotency-request-in-flight', ConflictException::class];
        yield 'rate limited' => [429, 'too-many-requests', RateLimitedException::class];
        yield 'service unavailable' => [503, 'standing-unavailable', ServiceUnavailableException::class];
        yield 'bad request' => [400, 'idempotency-key-invalid', ApiException::class];
        yield 'gone' => [410, 'cursor-expired', ApiException::class];
        yield 'outcome unknown' => [500, 'idempotency-outcome-unknown', ApiException::class];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[DataProvider('cases')]
    public function testEachCaseACallerBranchesOnHasItsOwnClass(int $status, string $slug, string $class): void
    {
        $exception = self::exception(self::problem($status, ['type' => 'https://appsolutely.io/problems/' . $slug, 'title' => 'T', 'status' => $status]));

        self::assertSame($class, $exception::class);
        self::assertTrue($exception->hasType($slug));
    }

    public function testAnAuthenticationRefusalKeepsTheChallenge(): void
    {
        $unauthenticated = self::exception(self::problem(401, ['type' => 'https://appsolutely.io/problems/unauthenticated', 'title' => 'Unauthenticated.', 'status' => 401], [
            'WWW-Authenticate' => 'Bearer error="invalid_token"',
        ]));
        $forbidden = self::exception(self::problem(403, ['type' => 'https://appsolutely.io/problems/missing-scope', 'title' => 'T', 'status' => 403], [
            'WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="orders:read"',
        ]));

        self::assertSame('Bearer error="invalid_token"', $unauthenticated->challenge);
        self::assertSame('Bearer error="insufficient_scope", scope="orders:read"', $forbidden->challenge);
    }

    public function testARateLimitedAnswerSaysHowLongToWaitAndWhatIsLeft(): void
    {
        $exception = self::exception(self::problem(429, ['type' => 'https://appsolutely.io/problems/too-many-requests', 'title' => 'Too many requests.', 'status' => 429], [
            'Retry-After' => '42',
            'RateLimit-Policy' => '"api:authenticated";q=60;w=60',
            'RateLimit' => '"api:authenticated";r=0;t=42',
        ]));

        self::assertInstanceOf(RateLimitedException::class, $exception);
        self::assertSame(42, $exception->retryAfter);
        self::assertSame(0, $exception->rateLimit->quota('api:authenticated')?->remaining);
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function retryAfterValues(): iterable
    {
        yield 'seconds' => ['120', 120];
        yield 'zero' => ['0', 0];
        yield 'an HTTP date' => ['Thu, 08 Oct 2026 12:00:30 GMT', 30];
        yield 'an HTTP date already past' => ['Thu, 08 Oct 2026 11:59:00 GMT', 0];
        yield 'negative' => ['-5', null];
        yield 'not a number' => ['soon', null];
        yield 'a decimal' => ['1.5', null];
    }

    #[DataProvider('retryAfterValues')]
    public function testRetryAfterIsReadAsSecondsOrAnHttpDate(string $value, ?int $seconds): void
    {
        $exception = self::exception(self::problem(503, ['type' => 'about:blank', 'title' => 'Service Unavailable', 'status' => 503], ['Retry-After' => $value]));

        self::assertInstanceOf(ServiceUnavailableException::class, $exception);
        self::assertSame($seconds, $exception->retryAfter);
    }

    public function testAnErrorThatIsNotAProblemKeepsItsRawBody(): void
    {
        $response = (new Psr17Factory())->createResponse(502, 'Bad Gateway')
            ->withHeader('Content-Type', 'text/html')
            ->withBody((new Psr17Factory())->createStream('<html>upstream down</html>'));

        $exception = self::exception($response, 'req-1');

        self::assertSame(ApiException::class, $exception::class);
        self::assertFalse($exception->isProblem);
        self::assertSame(502, $exception->status);
        self::assertSame('about:blank', $exception->type);
        self::assertSame('Bad Gateway', $exception->title);
        self::assertNull($exception->detail);
        self::assertSame([], $exception->problem);
        self::assertSame('<html>upstream down</html>', $exception->body);
        self::assertSame('req-1', $exception->requestId);
    }

    public function testAJsonErrorThatIsNotAProblemIsStillAnException(): void
    {
        $exception = self::exception(self::json(404, '{"message":"Not Found"}', 'application/json'));

        self::assertInstanceOf(NotFoundException::class, $exception);
        self::assertFalse($exception->isProblem);
        self::assertSame('{"message":"Not Found"}', $exception->body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function brokenProblems(): iterable
    {
        yield 'not JSON' => ['{"type":'];
        yield 'a JSON list' => ['[1,2]'];
        yield 'members of the wrong type' => ['{"type":5,"title":["x"],"detail":{},"instance":1,"errors":"x"}'];
        yield 'errors of the wrong shape' => ['{"type":"https://appsolutely.io/problems/validation-failed","title":"T","errors":{"a":"x","b":[1,"kept"]}}'];
    }

    #[DataProvider('brokenProblems')]
    public function testABrokenProblemBodyNeverFailsTheParse(string $body): void
    {
        $exception = self::exception(self::json(422, $body, 'application/problem+json; charset=utf-8'));

        self::assertSame(422, $exception->status);
        self::assertNotSame('', $exception->title);
        self::assertSame($body, $exception->body);
        foreach ($exception->errors as $messages) {
            self::assertSame(array_values(array_filter($messages, is_string(...))), $messages);
        }
    }

    public function testAProblemTypeDefaultsToAboutBlank(): void
    {
        $exception = self::exception(self::json(400, '{"title":"Bad"}', 'application/problem+json'));

        self::assertSame('about:blank', $exception->type);
        self::assertSame('Bad', $exception->title);
    }

    public function testTheHttpStatusWinsOverTheProblemsStatusMember(): void
    {
        $exception = self::exception(self::problem(404, ['type' => 'about:blank', 'title' => 'T', 'status' => 200]));

        self::assertSame(404, $exception->status);
        self::assertInstanceOf(NotFoundException::class, $exception);
    }

    public function testProseFromTheSiteReachesTheMessageOnlyAsShortPrintableText(): void
    {
        $exception = self::exception(self::problem(422, [
            'type' => 'about:blank',
            'title' => "Bad\r\nFORGED",
            'detail' => str_repeat('x', 500),
        ]));

        self::assertDoesNotMatchRegularExpression('/[^\x20-\x7E]/', $exception->getMessage());
        self::assertStringNotContainsString(str_repeat('x', 250), $exception->getMessage());
        self::assertSame("Bad\r\nFORGED", $exception->title);
    }

    private static function exception(ResponseInterface $response, ?string $requestId = null): ApiException
    {
        return ApiException::fromResponse($response, $requestId, new DateTimeImmutable(self::NOW));
    }

    /**
     * @param array<string, mixed> $problem
     * @param array<string, string> $headers
     */
    private static function problem(int $status, array $problem, array $headers = []): ResponseInterface
    {
        $response = self::json($status, json_encode($problem, JSON_THROW_ON_ERROR), 'application/problem+json');
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private static function json(int $status, string $body, string $contentType): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($factory->createStream($body));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Every Site API call goes to the configured origin with the credential of
 * the client it was made on, the headers the site documents, and an answer
 * read the same way.
 */
final class SiteApiTest extends TestCase
{
    private const string UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function testACallCarriesTheAdministratorTokenAndTheDocumentedHeaders(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json(['version' => 'v1']));

        $response = $provider->client()->api()->raw()->get('/api/v1/articles/a-1');

        $request = self::onlyRequest($provider);
        self::assertSame('GET', $request->getMethod());
        self::assertSame(FakeProvider::BASE_URL . '/api/v1/articles/a-1', (string) $request->getUri());
        self::assertSame('Bearer ' . FakeProvider::API_TOKEN, $request->getHeaderLine('Authorization'));
        self::assertSame('application/json, application/problem+json', $request->getHeaderLine('Accept'));
        self::assertStringStartsWith('appsolutely-sdk-php/', $request->getHeaderLine('User-Agent'));
        self::assertMatchesRegularExpression(self::UUID_V4, $request->getHeaderLine('X-Request-Id'));
        self::assertFalse($request->hasHeader('Idempotency-Key'));
        self::assertSame(['version' => 'v1'], $response->data);
    }

    public function testEachCallGetsAFreshRequestIdUnlessTheCallerGivesOne(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));
        $api = $provider->client()->api()->raw();

        $api->get('/api/v1/version');
        $api->get('/api/v1/version');
        $api->get('/api/v1/version', requestId: 'trace-123');

        $ids = array_map(static fn(RequestInterface $request): string => $request->getHeaderLine('X-Request-Id'), $provider->siteRequests());
        self::assertNotSame($ids[0], $ids[1]);
        self::assertSame('trace-123', $ids[2]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedRequestIds(): iterable
    {
        yield 'empty' => [''];
        yield 'a line break' => ["id\r\nX-Forged: 1"];
        yield 'a space' => ['two words'];
        yield 'longer than 255 characters' => [str_repeat('a', 256)];
    }

    #[DataProvider('refusedRequestIds')]
    public function testARequestIdThatCannotBeAHeaderIsRefused(string $requestId): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));

        $this->expectException(InvalidArgumentValueException::class);

        $provider->client()->api()->raw()->get('/api/v1/version', requestId: $requestId);
    }

    public function testWithoutAnAdministratorTokenACallCarriesNoCredential(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));

        $provider->client(apiToken: null)->api()->raw()->get('/api/v1/version');

        self::assertFalse(self::onlyRequest($provider)->hasHeader('Authorization'));
    }

    public function testACallAsAMemberCarriesTheMembersTokenAndLeavesTheAdministratorsAlone(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));
        $client = $provider->client();

        $client->forMember('member-access-token')->api()->raw()->get('/api/v1/me');
        $client->api()->raw()->get('/api/v1/orders');

        [$asMember, $asAdministrator] = $provider->siteRequests();
        self::assertSame('Bearer member-access-token', $asMember->getHeaderLine('Authorization'));
        self::assertSame('Bearer ' . FakeProvider::API_TOKEN, $asAdministrator->getHeaderLine('Authorization'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsendableTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'a line break' => ["token\r\nX-Forged: 1"];
        yield 'a space' => ['two words'];
    }

    #[DataProvider('unsendableTokens')]
    public function testAMemberTokenThatCannotBeSentIsRefused(string $token): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        (new FakeProvider())->client()->forMember($token);
    }

    public function testABaseUrlWithATrailingSlashIsNotDoubled(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));
        $config = $provider->config();

        (new Client(new Config(
            baseUrl: FakeProvider::BASE_URL . '/',
            issuer: FakeProvider::ISSUER,
            clientId: FakeProvider::CLIENT_ID,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $config->httpClient,
            requestFactory: $config->requestFactory,
            streamFactory: $config->streamFactory,
        )))->api()->raw()->get('/api/v1/version');

        self::assertSame(FakeProvider::BASE_URL . '/api/v1/version', (string) self::onlyRequest($provider)->getUri());
    }

    public function testTheQueryIsEncodedWithNullsLeftOutAndBooleansSpelledOut(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));

        $provider->client()->api()->raw()->get('/api/v1/orders', ['status' => 'paid', 'since' => null, 'q' => 'a b&c', 'mine' => true, 'archived' => false, 'n' => 3]);

        self::assertSame('status=paid&q=a%20b%26c&mine=true&archived=false&n=3', self::onlyRequest($provider)->getUri()->getQuery());
    }

    public function testAListInTheQueryIsSentOncePerValueAsAnArrayParameter(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));

        $provider->client()->api()->raw()->get('/api/v1/account-states', ['subjects' => ['m-1', 'm 2&3'], 'none' => []]);

        self::assertSame('subjects%5B%5D=m-1&subjects%5B%5D=m%202%263', self::onlyRequest($provider)->getUri()->getQuery());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedPaths(): iterable
    {
        yield 'relative' => ['api/v1/orders'];
        yield 'another host' => ['//evil.example.com/api/v1/orders'];
        yield 'an absolute URL' => ['https://evil.example.com/api/v1/orders'];
        yield 'a query' => ['/api/v1/orders?status=paid'];
        yield 'a fragment' => ['/api/v1/orders#x'];
        yield 'whitespace' => ['/api/v1/orders /x'];
        yield 'a line break' => ["/api/v1/orders\r\n"];
        yield 'a backslash' => ['/api/v1\\orders'];
    }

    #[DataProvider('refusedPaths')]
    public function testAPathThatWouldLeaveTheSiteOrBeReadDifferentlyIsRefused(string $path): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json([]));

        try {
            $provider->client()->api()->raw()->get($path);
            self::fail('The path was accepted.');
        } catch (InvalidArgumentValueException) {
            self::assertSame([], $provider->siteRequests());
        }
    }

    public function testAWriteSendsItsBodyAsJson(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1'], 201, ['Location' => FakeProvider::BASE_URL . '/api/v1/articles/a-1']));
        $api = $provider->client()->api()->raw();

        $created = $api->post('/api/v1/articles', ['title' => 'Hello / world', 'body' => 'é']);
        $api->patch('/api/v1/articles/a-1', []);
        $api->put('/api/v1/articles/a-1', ['title' => 'x']);
        $api->delete('/api/v1/articles/a-1');

        [$post, $patch, $put, $delete] = $provider->siteRequests();
        self::assertSame('POST', $post->getMethod());
        self::assertSame('application/json', $post->getHeaderLine('Content-Type'));
        self::assertSame('{"title":"Hello / world","body":"é"}', (string) $post->getBody());
        self::assertSame(['PATCH', '{}'], [$patch->getMethod(), (string) $patch->getBody()]);
        self::assertSame(['PUT', '{"title":"x"}'], [$put->getMethod(), (string) $put->getBody()]);
        self::assertSame(['DELETE', ''], [$delete->getMethod(), (string) $delete->getBody()]);
        self::assertFalse($delete->hasHeader('Content-Type'));
        self::assertSame(201, $created->status);
        self::assertSame(['id' => 'a-1'], $created->data);
        self::assertSame(FakeProvider::BASE_URL . '/api/v1/articles/a-1', $created->location);
    }

    public function testTheAnswerNamesItselfAndItsBudget(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->json(['id' => 'a-1'], 200, [
            'X-Request-Id' => 'answered-id',
            'RateLimit-Policy' => '"api:authenticated";q=120;w=60',
            'RateLimit' => '"api:authenticated";r=119;t=42',
        ]));

        $response = $provider->client()->api()->raw()->get('/api/v1/articles/a-1');

        self::assertSame('answered-id', $response->requestId);
        self::assertSame(119, $response->rateLimit->quota('api:authenticated')?->remaining);
        self::assertFalse($response->replayed);
        self::assertNull($response->idempotencyKey);
        self::assertSame(200, $response->response->getStatusCode());
    }

    public function testAnEmptyAnswerHasNoData(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->factory->createResponse(204));

        $response = $provider->client()->api()->raw()->delete('/api/v1/articles/a-1');

        self::assertSame(204, $response->status);
        self::assertNull($response->data);
    }

    public function testASuccessThatIsNotJsonIsRefused(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->factory->createResponse(200)->withBody($provider->factory->createStream('<html>')));

        $this->expectException(UnexpectedResponseException::class);

        $provider->client()->api()->raw()->get('/api/v1/version');
    }

    public function testARefusalBecomesItsExceptionWithTheRequestIdSent(): void
    {
        $provider = self::answering(fn(FakeProvider $provider): ResponseInterface => $provider->problem(404, 'not-found'));

        try {
            $provider->client()->api()->raw()->get('/api/v1/articles/missing', requestId: 'trace-9');
            self::fail('The refusal was not thrown.');
        } catch (NotFoundException $exception) {
            self::assertSame('trace-9', $exception->requestId);
            self::assertTrue($exception->hasType('not-found'));
        }
    }

    public function testDumpingAClientOrItsApiHidesEveryToken(): void
    {
        $client = (new FakeProvider())->client();
        $member = $client->forMember('member-access-token');

        foreach ([$client, $client->api(), $client->api()->raw(), $member, $member->api(), $member->api()->raw()] as $object) {
            ob_start();
            var_dump($object);
            $dumped = (string) ob_get_clean() . print_r($object, true);

            self::assertStringNotContainsString(FakeProvider::API_TOKEN, $dumped);
            self::assertStringNotContainsString('member-access-token', $dumped);
        }
    }

    public function testObjectsHoldingATokenRefuseToBeSerialized(): void
    {
        $client = (new FakeProvider())->client();
        $member = $client->forMember('member-access-token');

        foreach ([$client->api(), $client->api()->raw(), $member, $member->api(), $member->api()->raw()] as $object) {
            try {
                serialize($object);
                self::fail(get_debug_type($object) . ' was serialized.');
            } catch (NotSerializableException $exception) {
                self::assertStringNotContainsString('member-access-token', $exception->getMessage());
            }
        }
    }

    /**
     * A site that answers every API request with $answer.
     *
     * @param \Closure(FakeProvider, RequestInterface): ResponseInterface $answer
     */
    private static function answering(\Closure $answer): FakeProvider
    {
        $provider = new FakeProvider();
        $provider->site = static fn(RequestInterface $request): ResponseInterface => $answer($provider, $request);

        return $provider;
    }

    private static function onlyRequest(FakeProvider $provider): RequestInterface
    {
        $requests = $provider->siteRequests();
        self::assertCount(1, $requests);

        return $requests[0];
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Admin;

use Appsolutely\Sdk\Admin\AdminClient;
use Appsolutely\Sdk\Admin\AdminConfig;
use Appsolutely\Sdk\Admin\Model\Article;
use Appsolutely\Sdk\Admin\Model\Order;
use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class AdminResourcesTest extends TestCase
{
    private const array ARTICLE = ['id' => '0f6c1c36-0d3a-4e8e-9a4b-3f1f2a0b5c6d', 'title' => 'Launch', 'published_at' => '2026-10-16T08:00:00Z', 'status' => 1];

    private FakeProvider $provider;

    /** @var list<ResponseInterface> answers in order; the last keeps answering */
    private array $answers = [];

    protected function setUp(): void
    {
        $this->provider = new FakeProvider();
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['access_token' => 'admin-access', 'token_type' => 'Bearer', 'expires_in' => 600]);
        $this->provider->site = function (RequestInterface $request): ResponseInterface {
            return count($this->answers) > 1 ? array_shift($this->answers) : ($this->answers[0] ?? $this->provider->json([]));
        };
    }

    public function testAListIsReadAPageAtATimeAsModels(): void
    {
        $this->answers = [
            $this->provider->json(['data' => [self::ARTICLE], 'next_cursor' => 'c2']),
            $this->provider->json(['data' => [[...self::ARTICLE, 'id' => 'a2', 'status' => 0]]]),
        ];

        $articles = iterator_to_array($this->admin()->articles()->list(sort: 'published_at', order: 'desc', limit: 1), false);

        self::assertCount(2, $articles);
        self::assertInstanceOf(Article::class, $articles[0]);
        self::assertSame(Article::STATUS_PUBLISHED, $articles[0]->status);
        self::assertSame(Article::STATUS_DRAFT, $articles[1]->status);
        $site = $this->provider->siteRequests();
        self::assertSame(FakeProvider::BASE_URL . '/api/admin/v1/articles?sort=published_at&order=desc&limit=1', (string) $site[0]->getUri());
        self::assertStringContainsString('cursor=c2', (string) $site[1]->getUri());
    }

    public function testARecordIsReadWithItsVersion(): void
    {
        $this->answers = [$this->provider->json(self::ARTICLE, 200, ['ETag' => '"v7"'])];

        $read = $this->admin()->articles()->get(self::ARTICLE['id']);

        self::assertSame('Launch', $read->record->title);
        self::assertSame('"v7"', $read->etag);
        self::assertSame(FakeProvider::BASE_URL . '/api/admin/v1/articles/' . self::ARTICLE['id'], (string) $this->provider->siteRequests()[0]->getUri());
    }

    /**
     * Every read the document describes names its version; one that does not
     * is no answer a later update could be made over.
     */
    public function testAReadWithoutAVersionIsRefusedAsUnexpected(): void
    {
        $this->answers = [$this->provider->json(self::ARTICLE)];

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('ETag');

        $this->admin()->articles()->get(self::ARTICLE['id']);
    }

    public function testAnUpdateAndADeleteAreMadeOverTheVersionRead(): void
    {
        $this->answers = [
            $this->provider->json([...self::ARTICLE, 'title' => 'Launched'], 200, ['ETag' => '"v8"']),
            $this->provider->json([], 204),
        ];

        $updated = $this->admin()->articles()->update(self::ARTICLE['id'], '"v7"', ['title' => 'Launched']);
        $this->admin()->articles()->delete(self::ARTICLE['id'], '"v8"');

        [$patch, $delete] = $this->provider->siteRequests();
        self::assertSame('PATCH', $patch->getMethod());
        self::assertSame('"v7"', $patch->getHeaderLine('If-Match'));
        self::assertSame(['title' => 'Launched'], json_decode((string) $patch->getBody(), true));
        self::assertSame('"v8"', $updated->etag);
        self::assertSame('DELETE', $delete->getMethod());
        self::assertSame('"v8"', $delete->getHeaderLine('If-Match'));
    }

    public function testAVersionChangedSinceIsRefusedNotOverwritten(): void
    {
        $this->answers = [$this->provider->problem(412, 'precondition-failed')];

        try {
            $this->admin()->articles()->update(self::ARTICLE['id'], '"v7"', ['title' => 'Launched']);
            self::fail('The refusal was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame(412, $exception->status);
            self::assertTrue($exception->hasType('precondition-failed'));
        }
    }

    public function testAnUpdateWithoutAVersionIsRefusedBeforeSending(): void
    {
        try {
            $this->admin()->articles()->update(self::ARTICLE['id'], '', ['title' => 'Launched']);
            self::fail('The update was sent.');
        } catch (InvalidArgumentValueException) {
            self::assertSame([], $this->provider->siteRequests());
        }
    }

    public function testACreateAndARedeliveryAreSentOnceUnderAKey(): void
    {
        $this->answers = [
            $this->provider->json(self::ARTICLE, 201),
            $this->provider->json(['queued' => true], 200, ['Idempotent-Replayed' => 'true']),
        ];

        $created = $this->admin()->articles()->create(['title' => 'Launch', 'content' => 'Body.'], 'article-launch');
        $redelivered = $this->admin()->webhookDeliveries()->redeliver('7a1d', 'redeliver-7a1d');

        [$post, $redeliver] = $this->provider->siteRequests();
        self::assertSame('"article-launch"', $post->getHeaderLine('Idempotency-Key'));
        self::assertSame('Launch', $created->value->title);
        self::assertSame(FakeProvider::BASE_URL . '/api/admin/v1/webhook-deliveries/7a1d/actions/redeliver', (string) $redeliver->getUri());
        self::assertSame('"redeliver-7a1d"', $redeliver->getHeaderLine('Idempotency-Key'));
        self::assertSame(['queued' => true], $redelivered->value);
        self::assertTrue($redelivered->replayed);
    }

    public function testAnIdIsSentAsOneSegment(): void
    {
        $this->answers = [$this->provider->json(['id' => 'ORD 1'], 200, ['ETag' => '"v1"'])];

        $order = $this->admin()->orders()->get('ORD 1')->record;

        self::assertInstanceOf(Order::class, $order);
        self::assertSame(FakeProvider::BASE_URL . '/api/admin/v1/orders/ORD%201', (string) $this->provider->siteRequests()[0]->getUri());
    }

    /**
     * A server decodes an encoded slash before routing, so an id holding one
     * would name another path; it is refused before anything is sent.
     */
    public function testAnIdHoldingASlashIsRefused(): void
    {
        try {
            $this->admin()->orders()->get('ORD 1/2');
            self::fail('The id was sent.');
        } catch (InvalidArgumentValueException) {
            self::assertSame([], $this->provider->siteRequests());
        }
    }

    public function testADotSegmentIsNoId(): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        $this->admin()->orders()->get('..');
    }

    private function admin(): AdminClient
    {
        return new AdminClient(new AdminConfig(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            clientId: 'admin-credential-789',
            clientSecret: 'adm1n/s3cret+chars',
            scopes: ['admin:articles:read', 'admin:articles:write', 'admin:articles:delete', 'admin:orders:read', 'admin:webhook-deliveries:redeliver'],
            httpClient: $this->provider->http,
            requestFactory: $this->provider->factory,
            streamFactory: $this->provider->factory,
            cache: $this->provider->cache,
            clock: $this->provider->clock,
            retryPolicy: RetryPolicy::none(),
        ));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Testing;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\RateLimitedException;
use Appsolutely\Sdk\Exception\UnarrangedCallException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Exception\ValidationFailedException;
use Appsolutely\Sdk\Model\Article;
use Appsolutely\Sdk\Testing\FakeClient;
use Appsolutely\Sdk\Tests\Resource\ContentTest;
use Appsolutely\Sdk\Tests\Resource\MemberTest;
use PHPUnit\Framework\TestCase;

/**
 * FakeClient answers the Site API from arranged answers and records every
 * call, through the same client, the same problem parsing and the same
 * models as a real site's answers take.
 */
final class FakeClientTest extends TestCase
{
    public function testAnArrangedAnswerIsReadIntoTheModelAndTheCallIsRecorded(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetArticle, ContentTest::article('art-1'));

        $article = $fake->client()->api()->articles()->get('art-1');

        self::assertSame('art-1', $article->id);
        self::assertSame('2026-10-08T12:34:56+00:00', $article->publishedAt->format(DATE_ATOM));
        $call = $fake->lastCall(Operation::GetArticle);
        self::assertSame(['id' => 'art-1'], $call->pathParameters);
        self::assertSame([], $call->query);
        self::assertNull($call->body);
        self::assertSame(FakeClient::ADMINISTRATOR_TOKEN, $call->token);
    }

    public function testAWriteRecordsItsBodyAndItsKey(): void
    {
        $fake = (new FakeClient())->answer(Operation::CreateArticle, ContentTest::article('art-9'));

        $created = $fake->api()->articles()->create(['title' => 'Hello', 'content' => 'Hi'], idempotencyKey: 'import-42');
        $fake->answer(Operation::CreateArticle, ContentTest::article('art-10'));
        $fake->api()->articles()->create(['title' => 'Again', 'content' => 'Hi']);

        self::assertSame('art-9', $created->value->id);
        self::assertFalse($created->replayed);
        [$first, $second] = $fake->calls(Operation::CreateArticle);
        self::assertSame(['title' => 'Hello', 'content' => 'Hi'], $first->body);
        self::assertSame('import-42', $first->idempotencyKey);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/D', (string) $second->idempotencyKey);
    }

    public function testAReplayIsArrangedWithItsHeader(): void
    {
        $fake = (new FakeClient())->answer(Operation::CreateArticle, ContentTest::article(), headers: ['Idempotent-Replayed' => 'true']);

        self::assertTrue($fake->api()->articles()->create(['title' => 'Hello', 'content' => 'Hi'])->replayed);
    }

    public function testAMemberCallCarriesTheMembersToken(): void
    {
        $fake = (new FakeClient())->answer(Operation::ListMeAddresses, ['data' => [MemberTest::address()]]);

        $addresses = iterator_to_array($fake->forMember('member-7')->api()->me()->addresses()->list(order: 'desc'), false);

        self::assertSame('London', $addresses[0]->city);
        $call = $fake->lastCall(Operation::ListMeAddresses);
        self::assertSame('member-7', $call->token);
        self::assertSame(['order' => 'desc', 'limit' => '25'], $call->query);
    }

    public function testAnOperationAnyoneMayCallIsRecordedWithoutACredential(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetApiVersion, ['version' => 'v1', 'status' => 'current']);

        $fake->api()->version();

        self::assertNull($fake->lastCall(Operation::GetApiVersion)->token);
    }

    public function testAListIsPagedThroughTheArrangedPagesInOrder(): void
    {
        $fake = (new FakeClient())
            ->answerPage(Operation::ListArticles, [ContentTest::article('a')], nextCursor: 'c2')
            ->answerPage(Operation::ListArticles, [ContentTest::article('b')]);

        $ids = array_map(static fn(Article $article): string => $article->id, iterator_to_array($fake->api()->articles()->list(), false));

        self::assertSame(['a', 'b'], $ids);
        self::assertSame('c2', $fake->calls(Operation::ListArticles)[1]->query['cursor'] ?? null);
    }

    public function testTheLastArrangedAnswerKeepsAnswering(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetProduct, ContentTest::product('p-1'));

        $fake->api()->products()->get('p-1');
        $fake->api()->products()->get('p-1');

        self::assertCount(2, $fake->calls(Operation::GetProduct));
    }

    public function testARefusalIsThrownAsTheSitesProblemWouldBe(): void
    {
        $fake = (new FakeClient())->refuse(Operation::UpdateArticle, 'validation-failed', 422, detail: 'The title is too long.', members: ['errors' => ['title' => ['Too long.']]]);

        try {
            $fake->api()->articles()->update('art-1', ['title' => str_repeat('x', 300)]);
            self::fail('The refusal was not thrown.');
        } catch (ValidationFailedException $exception) {
            self::assertSame('https://appsolutely.io/problems/validation-failed', $exception->type);
            self::assertSame('The title is too long.', $exception->detail);
            self::assertSame(['title' => ['Too long.']], $exception->errors);
            self::assertSame($fake->lastCall(Operation::UpdateArticle)->requestId, $exception->requestId);
        }
    }

    public function testARefusalIsNotRetried(): void
    {
        $fake = (new FakeClient())->refuse(Operation::GetOrder, 'rate-limited', 429, headers: ['Retry-After' => '1']);

        try {
            $fake->api()->orders()->get('ord-1');
            self::fail('The refusal was not thrown.');
        } catch (RateLimitedException $exception) {
            self::assertSame(1, $exception->retryAfter);
        }
        self::assertCount(1, $fake->calls());
    }

    public function testAnswersAreQueuedPerOperation(): void
    {
        $fake = (new FakeClient())
            ->refuse(Operation::GetMeOrder, 'not-found', 404)
            ->answer(Operation::GetMeOrder, \Appsolutely\Sdk\Tests\Resource\RecordsTest::order('ord-2'));
        $orders = $fake->forMember()->api()->me()->orders();

        try {
            $orders->get('ord-1');
            self::fail('The refusal was not thrown.');
        } catch (NotFoundException) {
        }
        self::assertSame('ord-2', $orders->get('ord-2')->id);
    }

    public function testAnAnswerThatBreaksTheSchemaFailsAsARealOneWould(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetArticle, ['id' => 'art-1']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"title"');

        $fake->api()->articles()->get('art-1');
    }

    public function testACallWithNoArrangedAnswerFailsNamingTheOperation(): void
    {
        $fake = new FakeClient();

        try {
            $fake->api()->pages()->get('page-1');
            self::fail('The call was answered.');
        } catch (UnarrangedCallException $exception) {
            self::assertStringContainsString('getPage', $exception->getMessage());
        }
        self::assertSame(Operation::GetPage, $fake->calls()[0]->operation);
    }

    public function testARawCallOutsideTheDocumentFails(): void
    {
        $fake = new FakeClient();

        $this->expectException(UnarrangedCallException::class);
        $this->expectExceptionMessage('GET /api/v1/not-documented');

        $fake->api()->raw()->get('/api/v1/not-documented');
    }

    public function testNoCallsAreRecordedBeforeAnyAreMade(): void
    {
        $fake = new FakeClient();

        self::assertSame([], $fake->calls());
        $this->expectException(\LogicException::class);
        $fake->lastCall(Operation::GetMe);
    }

    public function testAMemberSyncPushIsAnsweredAndRecorded(): void
    {
        $fake = (new FakeClient())->answer(Operation::PushSyncMeAddress, ['results' => [['mutation_id' => 'm-1', 'applied' => true]]]);

        $push = $fake->forMember()->api()->sync()->addresses()->push([['mutation_id' => 'm-1', 'op' => 'delete', 'id' => 'addr-1', 'client_updated_at' => '2026-10-08T12:00:00Z']]);

        self::assertTrue($push->results[0]->applied);
        self::assertSame(FakeClient::MEMBER_TOKEN, $fake->lastCall(Operation::PushSyncMeAddress)->token);
        self::assertNull($fake->lastCall(Operation::PushSyncMeAddress)->idempotencyKey);
    }
}

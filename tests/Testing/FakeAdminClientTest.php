<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Testing;

use Appsolutely\Sdk\Admin\AdminOperation;
use Appsolutely\Sdk\Admin\Model\Article;
use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\ForbiddenException;
use Appsolutely\Sdk\Exception\UnarrangedCallException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Testing\FakeAdminClient;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FakeAdminClientTest extends TestCase
{
    private const array ARTICLE = ['id' => 'a-1', 'title' => 'Launch', 'published_at' => '2026-10-16T08:00:00Z', 'status' => 1];

    public function testAnArrangedAnswerIsReadIntoTheModelWithItsVersionAndTheCallIsRecorded(): void
    {
        $fake = (new FakeAdminClient())->answer(AdminOperation::GetArticle, self::ARTICLE, etag: '"v1"');

        $read = $fake->client()->articles()->get('a-1');

        self::assertInstanceOf(Article::class, $read->record);
        self::assertSame('"v1"', $read->etag);
        $call = $fake->lastCall(AdminOperation::GetArticle);
        self::assertSame(['article' => 'a-1'], $call->pathParameters);
        self::assertSame(FakeAdminClient::ACCESS_TOKEN, $call->token);
    }

    public function testAWriteRecordsItsBodyItsKeyAndTheVersionItWasMadeOver(): void
    {
        $fake = (new FakeAdminClient())
            ->answer(AdminOperation::CreateArticle, self::ARTICLE)
            ->answer(AdminOperation::UpdateArticle, self::ARTICLE, etag: '"v2"')
            ->answer(AdminOperation::DeleteArticle);

        $fake->client()->articles()->create(['title' => 'Launch', 'content' => 'Body.'], 'launch-key');
        $fake->client()->articles()->update('a-1', '"v1"', ['title' => 'Launched']);
        $fake->client()->articles()->delete('a-1', '"v2"');

        self::assertSame('launch-key', $fake->lastCall(AdminOperation::CreateArticle)->idempotencyKey);
        self::assertSame(['title' => 'Launched'], $fake->lastCall(AdminOperation::UpdateArticle)->body);
        self::assertSame('"v1"', $fake->lastCall(AdminOperation::UpdateArticle)->ifMatch);
        self::assertSame('"v2"', $fake->lastCall(AdminOperation::DeleteArticle)->ifMatch);
    }

    public function testAListIsPagedThroughTheArrangedPagesInOrder(): void
    {
        $fake = (new FakeAdminClient())
            ->answerPage(AdminOperation::ListArticles, [self::ARTICLE], 'next')
            ->answerPage(AdminOperation::ListArticles, [[...self::ARTICLE, 'id' => 'a-2']]);

        $ids = array_map(static fn(Article $article): string => $article->id, iterator_to_array($fake->client()->articles()->list(), false));

        self::assertSame(['a-1', 'a-2'], $ids);
        self::assertSame('next', $fake->calls(AdminOperation::ListArticles)[1]->query['cursor'] ?? null);
    }

    public function testARefusalIsThrownAsTheSitesProblemWouldBe(): void
    {
        $fake = (new FakeAdminClient())->refuse(AdminOperation::UpdateArticle, 'precondition-failed', 412, 'The record changed since it was read.');

        try {
            $fake->client()->articles()->update('a-1', '"v1"', ['title' => 'Launched']);
            self::fail('The refusal was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame(412, $exception->status);
            self::assertTrue($exception->hasType('precondition-failed'));
        }
    }

    public function testAMissingScopeIsForbiddenNamingIt(): void
    {
        $fake = (new FakeAdminClient())->refuse(AdminOperation::ListOrders, 'insufficient-scope', 403, 'The credential lacks a scope.', members: ['required' => 'admin:orders:read']);

        $this->expectException(ForbiddenException::class);

        iterator_to_array($fake->client()->orders()->list());
    }

    public function testAnAnswerThatBreaksTheSchemaFailsAsARealOneWould(): void
    {
        $fake = (new FakeAdminClient())->answer(AdminOperation::GetArticle, ['id' => 'a-1']);

        $this->expectException(UnexpectedResponseException::class);

        $fake->client()->articles()->get('a-1');
    }

    public function testACallWithNoArrangedAnswerFailsNamingTheOperation(): void
    {
        $this->expectException(UnarrangedCallException::class);
        $this->expectExceptionMessage(AdminOperation::GetPage->value);

        (new FakeAdminClient())->client()->pages()->get('p-1');
    }

    public function testARawCallOutsideTheTypedOperationsFails(): void
    {
        $this->expectException(UnarrangedCallException::class);

        (new FakeAdminClient())->client()->raw()->get('/api/admin/v1/menus');
    }

    public function testARefusalCannotReuseAStandardMember(): void
    {
        $this->expectException(LogicException::class);

        (new FakeAdminClient())->refuse(AdminOperation::GetPage, 'not-found', 404, 'Not found.', members: ['status' => 404]);
    }
}

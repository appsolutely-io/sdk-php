<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Testing;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Exception\ForbiddenException;
use Appsolutely\Sdk\Exception\NotFoundException;
use Appsolutely\Sdk\Exception\RateLimitedException;
use Appsolutely\Sdk\Exception\UnarrangedCallException;
use Appsolutely\Sdk\Exception\UnauthenticatedException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Exception\ValidationFailedException;
use Appsolutely\Sdk\Model\Product;
use Appsolutely\Sdk\Testing\FakeClient;
use Appsolutely\Sdk\Tests\Resource\MemberTest;
use Appsolutely\Sdk\Tests\Resource\OrdersTest;
use Appsolutely\Sdk\Tests\Resource\ProductsTest;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $fake = (new FakeClient())->answer(Operation::GetProduct, ProductsTest::product('prod-1'));

        $product = $fake->client()->backend()->products()->get('prod-1');

        self::assertSame('prod-1', $product->id);
        self::assertSame(1900, $product->price);
        $call = $fake->lastCall(Operation::GetProduct);
        self::assertSame(['id' => 'prod-1'], $call->pathParameters);
        self::assertSame([], $call->query);
        self::assertNull($call->body);
        self::assertSame(FakeClient::BACKEND_TOKEN, $call->token);
        self::assertCount(1, $fake->calls());
    }

    public function testAWriteRecordsItsBodyAndItsKey(): void
    {
        $fake = (new FakeClient())->answer(Operation::CreateFulfilment, self::fulfilment('tok-9'));

        $fulfilled = $fake->backend()->fulfilments()->create('tok-9', 'LICENCE-1', 'email', idempotencyKey: 'import-42');
        $fake->answer(Operation::CreateFulfilment, self::fulfilment('tok-10'));
        $fake->backend()->fulfilments()->create('tok-10', 'LICENCE-2');

        self::assertSame('tok-9', $fulfilled->value->token);
        self::assertFalse($fulfilled->replayed);
        [$first, $second] = $fake->calls(Operation::CreateFulfilment);
        self::assertSame(['token' => 'tok-9', 'delivery_payload' => 'LICENCE-1', 'channel' => 'email'], $first->body);
        self::assertSame('import-42', $first->idempotencyKey);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/D', (string) $second->idempotencyKey);
    }

    public function testAReplayIsArrangedWithItsHeader(): void
    {
        $fake = (new FakeClient())->answer(Operation::CreateFulfilment, self::fulfilment(), headers: ['Idempotent-Replayed' => 'true']);

        self::assertTrue($fake->backend()->fulfilments()->create('tok-1', 'LICENCE-1')->replayed);
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
            ->answerPage(Operation::ListProducts, [ProductsTest::product('a')], nextCursor: 'c2')
            ->answerPage(Operation::ListProducts, [ProductsTest::product('b')]);

        $ids = array_map(static fn(Product $product): string => $product->id, iterator_to_array($fake->backend()->products()->list(), false));

        self::assertSame(['a', 'b'], $ids);
        self::assertSame('c2', $fake->calls(Operation::ListProducts)[1]->query['cursor'] ?? null);
    }

    public function testTheLastArrangedAnswerKeepsAnswering(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetProduct, ProductsTest::product('p-1'));

        $fake->backend()->products()->get('p-1');
        $fake->backend()->products()->get('p-1');

        self::assertCount(2, $fake->calls(Operation::GetProduct));
    }

    public function testARefusalIsThrownAsTheSitesProblemWouldBe(): void
    {
        $fake = (new FakeClient())->refuse(Operation::UpdateMeAddress, 'validation-failed', 422, 'The request did not pass validation.', detail: 'The city is too long.', members: ['errors' => ['city' => ['Too long.']]]);

        try {
            $fake->forMember()->api()->me()->addresses()->update('addr-1', ['city' => str_repeat('x', 300)]);
            self::fail('The refusal was not thrown.');
        } catch (ValidationFailedException $exception) {
            self::assertSame('https://appsolutely.io/problems/validation-failed', $exception->type);
            self::assertSame('The request did not pass validation.', $exception->title);
            self::assertSame('The city is too long.', $exception->detail);
            self::assertSame(['city' => ['Too long.']], $exception->errors);
            self::assertSame($fake->lastCall(Operation::UpdateMeAddress)->requestId, $exception->requestId);
        }
    }

    public function testAnEmptyDetailIsLeftOutAsTheSiteLeavesItOut(): void
    {
        $fake = (new FakeClient())->refuse(Operation::GetMeOrder, 'not-found', 404, 'Not found.', detail: '');

        try {
            $fake->forMember()->api()->me()->orders()->get('ord-1');
            self::fail('The refusal was not thrown.');
        } catch (NotFoundException $exception) {
            self::assertArrayNotHasKey('detail', $exception->problem);
            self::assertStringNotContainsString('"detail"', $exception->body);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function standardMembers(): iterable
    {
        foreach (['type', 'title', 'status', 'detail', 'instance'] as $member) {
            yield $member => [$member];
        }
    }

    #[DataProvider('standardMembers')]
    public function testARefusalCannotReuseAStandardMemberAsAnExtension(string $member): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($member);

        (new FakeClient())->refuse(Operation::GetMeOrder, 'not-found', 404, 'Not found.', members: [$member => 'x']);
    }

    public function testARefused401CarriesTheBearerChallengeTheSiteSends(): void
    {
        $fake = (new FakeClient())
            ->refuse(Operation::GetMeOrder, 'unauthenticated', 401, 'Unauthenticated.')
            ->refuse(Operation::GetApiVersion, 'unauthenticated', 401, 'Unauthenticated.');

        try {
            $fake->forMember()->api()->me()->orders()->get('ord-1');
            self::fail('The refusal was not thrown.');
        } catch (UnauthenticatedException $exception) {
            self::assertSame('Bearer error="invalid_token"', $exception->challenge);
        }

        try {
            $fake->api()->version();
            self::fail('The refusal was not thrown.');
        } catch (UnauthenticatedException $exception) {
            self::assertSame('Bearer', $exception->challenge);
        }
    }

    public function testAMissingScopeCarriesTheChallengeNamingItAndOtherForbiddensNone(): void
    {
        $fake = (new FakeClient())
            ->refuse(Operation::GetMeOrder, 'insufficient-scope', 403, 'Forbidden.', members: ['required' => 'orders:read', 'granted' => ['profile']])
            ->refuse(Operation::GetApiVersion, 'forbidden', 403, 'Forbidden.');

        try {
            $fake->forMember()->api()->me()->orders()->get('ord-1');
            self::fail('The refusal was not thrown.');
        } catch (ForbiddenException $exception) {
            self::assertSame('Bearer error="insufficient_scope", scope="orders:read"', $exception->challenge);
        }

        try {
            $fake->api()->version();
            self::fail('The refusal was not thrown.');
        } catch (ForbiddenException $exception) {
            self::assertNull($exception->challenge);
        }
    }

    public function testAnArrangedChallengeIsKept(): void
    {
        $fake = (new FakeClient())->refuse(Operation::GetMeOrder, 'unauthenticated', 401, 'Unauthenticated.', headers: ['www-authenticate' => 'Bearer realm="site"']);

        try {
            $fake->forMember()->api()->me()->orders()->get('ord-1');
            self::fail('The refusal was not thrown.');
        } catch (UnauthenticatedException $exception) {
            self::assertSame('Bearer realm="site"', $exception->challenge);
        }
    }

    public function testARefusalIsNotRetried(): void
    {
        $fake = (new FakeClient())->refuse(Operation::GetMeOrder, 'rate-limited', 429, 'Too many requests.', headers: ['Retry-After' => '1']);

        try {
            $fake->forMember()->api()->me()->orders()->get('ord-1');
            self::fail('The refusal was not thrown.');
        } catch (RateLimitedException $exception) {
            self::assertSame(1, $exception->retryAfter);
        }
        self::assertCount(1, $fake->calls());
    }

    public function testAnswersAreQueuedPerOperation(): void
    {
        $fake = (new FakeClient())
            ->refuse(Operation::GetMeOrder, 'not-found', 404, 'Not found.')
            ->answer(Operation::GetMeOrder, OrdersTest::order('ord-2'));
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
        $fake = (new FakeClient())->answer(Operation::GetProduct, ['id' => 'prod-1']);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"type"');

        $fake->backend()->products()->get('prod-1');
    }

    public function testACallWithNoArrangedAnswerFailsNamingTheOperation(): void
    {
        $fake = new FakeClient();

        try {
            $fake->backend()->products()->get('prod-1');
            self::fail('The call was answered.');
        } catch (UnarrangedCallException $exception) {
            self::assertStringContainsString('getProduct', $exception->getMessage());
        }
        self::assertSame(Operation::GetProduct, $fake->calls()[0]->operation);
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

    /**
     * @return array<string, mixed>
     */
    private static function fulfilment(string $token = 'tok-1'): array
    {
        return ['token' => $token, 'status' => 'delivered', 'delivered_at' => '2026-10-08T12:00:00Z'];
    }

    public function testAMembersSignInIsNotAnsweredWithTheBackendsToken(): void
    {
        $fake = new FakeClient();

        $this->expectException(UnarrangedCallException::class);

        $oidc = $fake->client()->oidc();
        $oidc->exchangeCode('member-code', $oidc->authorizationUrl('https://app.test/callback'));
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

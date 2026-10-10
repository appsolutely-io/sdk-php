<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Api\Audience;
use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * A keyed write is sent as a POST under an Idempotency-Key and nothing
 * else: an operation that would need another method or a query is refused
 * before anything is sent, rather than sent without them.
 */
final class CallerTest extends TestCase
{
    public function testAKeyedWriteWithAQueryIsRefusedBeforeAnythingIsSent(): void
    {
        $provider = new FakeProvider();
        $caller = new Caller($provider->client()->api()->raw(), Audience::Administrator);

        try {
            $caller->send(Operation::CreateArticle, query: ['draft' => true], body: ['title' => 'Hello']);
            self::fail('A keyed write with a query was sent.');
        } catch (LogicException $exception) {
            self::assertStringContainsString(Operation::CreateArticle->value, $exception->getMessage());
        }

        self::assertSame([], $provider->siteRequests());
    }

    public function testEveryKeyedOperationIsAPost(): void
    {
        foreach (Operation::cases() as $operation) {
            if ($operation->takesIdempotencyKey()) {
                self::assertSame('POST', $operation->method(), $operation->value);
            }
        }
    }
}

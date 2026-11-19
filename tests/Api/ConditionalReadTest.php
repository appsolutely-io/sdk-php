<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Exception\ApiException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ConditionalReadTest extends TestCase
{
    public function testAValidatorIsSentUnchangedAndANotModifiedAnswerHasNoData(): void
    {
        $provider = new FakeProvider();
        $provider->site = static fn(RequestInterface $request): ResponseInterface => $provider->factory->createResponse(304)->withHeader(Header::ETAG, 'W/"current"');

        $answer = $provider->client()->forMember('member-access-token')->api()->me()->getConditional('W/"previous", "current"');

        self::assertSame('W/"previous", "current"', $provider->siteRequests()[0]->getHeaderLine(Header::IF_NONE_MATCH));
        self::assertSame(304, $answer->response->status);
        self::assertTrue($answer->notModified());
        self::assertNull($answer->value);
        self::assertSame('W/"current"', $answer->response->etag());
    }

    public function testAChangedConditionalReadStillDecodesItsTypedPage(): void
    {
        $provider = new FakeProvider();
        $provider->site = static fn(RequestInterface $request): ResponseInterface => $provider->json(['data' => []], 200, [Header::ETAG => '"new"']);

        $answer = $provider->client()->forMember('member-access-token')->api()->me()->entitlements()->listConditional('"old"');

        self::assertFalse($answer->notModified());
        self::assertNotNull($answer->value);
        self::assertSame([], $answer->value->items);
        self::assertSame('"new"', $answer->response->etag());
    }

    public function testUnconditionalReadsDoNotAcceptAnUnsolicited304(): void
    {
        $provider = new FakeProvider();
        $provider->site = static fn(RequestInterface $request): ResponseInterface => $provider->factory->createResponse(304);

        $this->expectException(ApiException::class);
        $provider->client()->forMember('member-access-token')->api()->me()->get();
    }

    public function testInboxOrganisationAndDigest304AnswersDoNotCreateEmptyModels(): void
    {
        $provider = new FakeProvider();
        $provider->site = static fn(RequestInterface $request): ResponseInterface => $provider->factory->createResponse(304)->withHeader(Header::ETAG, '"unchanged"')->withBody($provider->factory->createStream('not-json'));
        $me = $provider->client()->forMember('member-access-token')->api()->me();

        $results = [
            $me->messages()->listConditional('"cached"', 10, 'page/one'),
            $me->organisations()->listConditional('"cached"', 10, 'page/one'),
            $me->realtime()->digestConditional('"cached"'),
        ];
        foreach ($results as $result) {
            self::assertTrue($result->notModified());
            self::assertNull($result->value);
            self::assertNull($result->response->data);
            self::assertSame('"unchanged"', $result->response->etag());
        }
        $requests = $provider->siteRequests();
        self::assertSame('limit=10&cursor=page%2Fone', $requests[0]->getUri()->getQuery());
        self::assertSame('limit=10&cursor=page%2Fone', $requests[1]->getUri()->getQuery());
        self::assertSame('/api/v1/me/realtime/digest', $requests[2]->getUri()->getPath());
    }

    public function testAValidatorCannotInjectAnotherHeader(): void
    {
        $provider = new FakeProvider();
        $this->expectException(InvalidArgumentValueException::class);
        $provider->client()->forMember('member-access-token')->api()->me()->getConditional("\"tag\"\r\nInjected: value");
    }
}

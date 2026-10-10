<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Api\RateLimit;
use Appsolutely\Sdk\Api\RateLimitQuota;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `RateLimit-Policy` and `RateLimit` as draft-ietf-httpapi-ratelimit-headers-11
 * defines them: Structured Field lists (RFC 9651) whose items name a quota
 * and whose parameters carry its numbers.
 */
final class RateLimitTest extends TestCase
{
    public function testAPolicyAndItsRemainderAreJoinedByName(): void
    {
        $rateLimit = RateLimit::fromHeaders('"api:authenticated";q=120;w=60', '"api:authenticated";r=119;t=42');

        self::assertEquals(
            ['api:authenticated' => new RateLimitQuota('api:authenticated', quota: 120, window: 60, remaining: 119, reset: 42)],
            $rateLimit->quotas,
        );
        self::assertSame(119, $rateLimit->quota('api:authenticated')?->remaining);
        self::assertNull($rateLimit->quota('api'));
    }

    public function testSeveralQuotasAreKeptApart(): void
    {
        $rateLimit = RateLimit::fromHeaders(
            '"api:authenticated";q=60;w=60, "api:refused-credentials";q=30;w=60',
            '"api:authenticated";r=59;t=42,"api:refused-credentials";r=0;t=17',
        );

        self::assertSame(['api:authenticated', 'api:refused-credentials'], array_keys($rateLimit->quotas));
        self::assertEquals(new RateLimitQuota('api:refused-credentials', 30, 60, 0, 17), $rateLimit->quota('api:refused-credentials'));
    }

    public function testTheExhaustedQuotaThatTurnsOverLastSaysHowLongToWait(): void
    {
        $rateLimit = RateLimit::fromHeaders('', '"a";r=0;t=5, "b";r=3;t=50, "c";r=0;t=9');

        self::assertSame('c', $rateLimit->exhausted()?->name);
        self::assertNull(RateLimit::fromHeaders('', '"a";r=1;t=5')->exhausted());
    }

    public function testAQuotaOnlyOneHeaderNamesKeepsTheOtherNumbersUnknown(): void
    {
        $rateLimit = RateLimit::fromHeaders('"policy-only";q=10;w=1', '"limit-only";r=4;t=1');

        self::assertEquals(new RateLimitQuota('policy-only', quota: 10, window: 1), $rateLimit->quota('policy-only'));
        self::assertEquals(new RateLimitQuota('limit-only', remaining: 4, reset: 1), $rateLimit->quota('limit-only'));
    }

    public function testTokensEscapesAndUnknownParametersAreRead(): void
    {
        $rateLimit = RateLimit::fromHeaders(
            'default;q=100;w=10;qu="requests";pk=:cHsdsRa894==:',
            '"a \"b\" \\\\ c";r=1;t=2;x=?1;y=1.5',
        );

        self::assertSame(100, $rateLimit->quota('default')?->quota);
        self::assertSame(1, $rateLimit->quota('a "b" \\ c')?->remaining);
    }

    public function testNoHeadersIsNoQuota(): void
    {
        self::assertSame([], RateLimit::fromHeaders('', '')->quotas);
    }

    /**
     * RFC 9651 section 4.2: a field that fails to parse is ignored whole.
     *
     * @return iterable<string, array{string}>
     */
    public static function unparseable(): iterable
    {
        yield 'unterminated string' => ['"api;r=1;t=2'];
        yield 'item that is not a string or a token' => ['1;r=1'];
        yield 'parameter without a key' => ['"a";=1'];
        yield 'integer of sixteen digits' => ['"a";r=1234567890123456'];
        yield 'trailing comma' => ['"a";r=1,'];
        yield 'garbage after the item' => ['"a" junk'];
        yield 'control character' => ["\"a\x01\";r=1"];
        yield 'not text at all' => ["\xFF\xFE"];
    }

    #[DataProvider('unparseable')]
    public function testAnUnparseableHeaderIsIgnoredWithoutFailing(string $header): void
    {
        $rateLimit = RateLimit::fromHeaders('"kept";q=5;w=1', $header);

        self::assertSame(['kept'], array_keys($rateLimit->quotas));
        self::assertNull($rateLimit->quota('kept')?->remaining);
    }

    public function testANumberOfTheWrongKindIsUnknownRatherThanGuessed(): void
    {
        $rateLimit = RateLimit::fromHeaders('"a";q="120";w=-1', '"a";r=1.5;t=?1');

        self::assertEquals(new RateLimitQuota('a'), $rateLimit->quota('a'));
    }
}

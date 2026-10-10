<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Webhooks\Events;

use Appsolutely\Sdk\Tests\Support\FrozenClock;
use Appsolutely\Sdk\Webhooks\Events\PingEvent;
use Appsolutely\Sdk\Webhooks\Events\TypedEvent;
use Appsolutely\Sdk\Webhooks\Events\UnknownEvent;
use Appsolutely\Sdk\Webhooks\EventType;
use Appsolutely\Sdk\Webhooks\Verifier;
use PHPUnit\Framework\TestCase;
use StandardWebhooks\Webhook;

/**
 * Each fixture under Fixtures/ is the `data` of one delivery as the site
 * writes it: the fields, their order, null where the site sends null, times
 * as RFC 3339 UTC to the second, ids as strings and amounts as integer minor
 * units. Record events carry the record exactly as the site's REST API
 * serves it, built from the same payload definition. The fixture's bytes go
 * into a body that is signed with the Standard Webhooks reference library
 * and verified, so every case runs the path a real delivery takes.
 */
final class TypedEventTest extends TestCase
{
    private const string SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    private const string ID = 'msg_01k6zq3n5m8r2t4v6w8y0a2c4e';

    private static function deliver(string $type, string $data, string $mode = 'production'): TypedEvent
    {
        $clock = new FrozenClock();
        $body = sprintf(
            '{"id":"%s","type":"%s","timestamp":"2026-10-08T11:59:58Z","version":1,"mode":"%s","data":%s}',
            self::ID,
            $type,
            $mode,
            $data,
        );
        $signature = (new Webhook(self::SECRET))->sign(self::ID, $clock->timestamp(), $body);
        self::assertIsString($signature);

        $envelope = (new Verifier([self::SECRET], $clock))->verify($body, [
            'webhook-id' => self::ID,
            'webhook-timestamp' => (string) $clock->timestamp(),
            'webhook-signature' => $signature,
        ]);

        return TypedEvent::from($envelope);
    }

    private static function fixture(string $name): string
    {
        $data = file_get_contents(__DIR__ . '/Fixtures/' . $name . '.json');
        self::assertIsString($data);

        return trim($data);
    }

    public function testThePingCarriesTheSubscriptionsReference(): void
    {
        $event = self::deliver(EventType::WEBHOOK_PING, self::fixture('ping'), 'test');

        self::assertInstanceOf(PingEvent::class, $event);
        self::assertSame('9d3f2b1c-5e7a-4c8b-a1f0-2e6d4b8c0a97', $event->subscriptionId);
        self::assertSame([], $event->extra);
        self::assertSame(self::ID, $event->envelope->id);
        self::assertSame('webhook.ping', $event->envelope->type);
        self::assertSame('test', $event->envelope->mode);
        self::assertSame('2026-10-08T11:59:58+00:00', $event->envelope->timestamp->format(DATE_RFC3339));
    }

    public function testAnUnknownTypeIsAGenericEventWithItsDataStillReadable(): void
    {
        $event = self::deliver('invoice.issued', '{"id":"inv_1","amount":1200,"currency":"EUR","lines":[{"sku":"a"}]}');

        self::assertInstanceOf(UnknownEvent::class, $event);
        self::assertSame('invoice.issued', $event->envelope->type);
        self::assertSame(['id' => 'inv_1', 'amount' => 1200, 'currency' => 'EUR', 'lines' => [['sku' => 'a']]], $event->envelope->data);
    }

    public function testAnUnknownTypeWithEmptyDataIsAGenericEvent(): void
    {
        $event = self::deliver('member.waved', '{}');

        self::assertInstanceOf(UnknownEvent::class, $event);
        self::assertSame([], $event->envelope->data);
    }

    public function testAFieldTheSiteAddsLaterIsKeptAsExtra(): void
    {
        $event = self::deliver(EventType::WEBHOOK_PING, '{"subscription_id":"s_1","sent_by":"operator"}');

        self::assertInstanceOf(PingEvent::class, $event);
        self::assertSame(['sent_by' => 'operator'], $event->extra);
    }
}

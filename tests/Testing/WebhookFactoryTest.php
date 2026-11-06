<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Testing;

use Appsolutely\Sdk\Clock\SystemClock;
use Appsolutely\Sdk\Testing\WebhookFactory;
use Appsolutely\Sdk\Tests\Support\FrozenClock;
use Appsolutely\Sdk\Webhooks\Verifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StandardWebhooks\Webhook;

final class WebhookFactoryTest extends TestCase
{
    private const string SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    private const string OLD_SECRET = 'whsec_C2FVsBQIhrscChlQIMV+b5sSYspob7oD';

    public function testTheReferenceLibraryAcceptsWhatTheFactorySigns(): void
    {
        $delivery = (new WebhookFactory([self::SECRET], new SystemClock()))->make('entitlement.granted', ['member' => 'm_1']);

        $payload = (new Webhook(self::SECRET))->verify($delivery->body, $delivery->headers);

        self::assertIsArray($payload);
        self::assertSame('entitlement.granted', $payload['type']);
    }

    public function testTheBodyIsTheServersEnvelopeByteForByte(): void
    {
        $clock = new FrozenClock();
        $factory = new WebhookFactory([self::SECRET], $clock);

        $delivery = $factory->make(
            'entitlement.granted',
            ['url' => 'https://example.com/a/b', 'name' => 'Zoë'],
            'test',
            'msg_fixed',
            new DateTimeImmutable('2026-10-08T13:59:58.5+02:00'),
        );

        self::assertSame(
            '{"id":"msg_fixed","type":"entitlement.granted","timestamp":"2026-10-08T11:59:58.500000Z","version":1,"mode":"test","data":{"url":"https://example.com/a/b","name":"Zoë"}}',
            $delivery->body,
        );
        self::assertSame('msg_fixed', $delivery->id);
        self::assertSame('msg_fixed', $delivery->headers['webhook-id']);
        self::assertSame((string) $clock->timestamp(), $delivery->headers['webhook-timestamp']);
    }

    public function testEmptyDataIsAnObjectOnTheWire(): void
    {
        $delivery = (new WebhookFactory([self::SECRET], new FrozenClock()))->make('ping');

        self::assertStringEndsWith('"data":{}}', $delivery->body);
    }

    public function testEachSecretAddsItsOwnSignature(): void
    {
        $clock = new FrozenClock();
        $delivery = (new WebhookFactory([self::OLD_SECRET, self::SECRET], $clock))->make('ping');

        $old = (new Webhook(self::OLD_SECRET))->sign($delivery->id, $clock->timestamp(), $delivery->body);
        $new = (new Webhook(self::SECRET))->sign($delivery->id, $clock->timestamp(), $delivery->body);
        self::assertIsString($old);
        self::assertIsString($new);
        self::assertSame($old . ' ' . $new, $delivery->headers['webhook-signature']);
        self::assertSame($delivery->id, (new Verifier([self::SECRET], $clock))->verify($delivery->body, $delivery->headers)->id);
    }

    public function testGeneratedIdsHaveTheServersShape(): void
    {
        $factory = new WebhookFactory([self::SECRET], new FrozenClock());

        $first = $factory->make('ping')->id;
        $second = $factory->make('ping')->id;

        self::assertMatchesRegularExpression('/^msg_[0-9a-hjkmnp-tv-z]{26}$/', $first);
        self::assertNotSame($first, $second);
    }

    public function testAnExplicitAttemptTimestampCanBeSigned(): void
    {
        $clock = new FrozenClock();
        $delivery = (new WebhookFactory([self::SECRET], $clock))->make('ping', attemptedAt: $clock->timestamp() - 3600);

        self::assertSame((string) ($clock->timestamp() - 3600), $delivery->headers['webhook-timestamp']);
    }
}

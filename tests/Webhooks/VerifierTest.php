<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Webhooks;

use Appsolutely\Sdk\Exception\AppsolutelyException;
use Appsolutely\Sdk\Tests\Support\FrozenClock;
use Appsolutely\Sdk\Webhooks\InvalidSecretException;
use Appsolutely\Sdk\Webhooks\Verifier;
use Appsolutely\Sdk\Webhooks\WebhookVerificationException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StandardWebhooks\Webhook;

/**
 * Signatures here are produced by the standard-webhooks reference library,
 * the one aio signs with, so agreement with it is what is tested.
 */
final class VerifierTest extends TestCase
{
    private const string SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    private const string OLD_SECRET = 'whsec_C2FVsBQIhrscChlQIMV+b5sSYspob7oD';
    private const string ID = 'msg_01k6zq3n5m8r2t4v6w8y0a2c4e';
    private const string BODY = '{"id":"msg_01k6zq3n5m8r2t4v6w8y0a2c4e","type":"entitlement.granted","timestamp":"2026-10-08T11:59:58.123456Z","version":1,"mode":"production","data":{"member":"m_1","url":"https://example.com/a/b","name":"Zoë"}}';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
    }

    /**
     * @param list<string> $secrets
     * @return array<string, string>
     */
    private function headers(array $secrets, ?int $timestamp = null, string $body = self::BODY): array
    {
        $timestamp ??= $this->clock->timestamp();

        return [
            'webhook-id' => self::ID,
            'webhook-timestamp' => (string) $timestamp,
            'webhook-signature' => implode(' ', array_map(
                static fn(string $secret): string => self::sign($secret, $timestamp, $body),
                $secrets,
            )),
        ];
    }

    private static function sign(string $secret, int $timestamp, string $body): string
    {
        $signature = (new Webhook($secret))->sign(self::ID, $timestamp, $body);
        self::assertIsString($signature);

        return $signature;
    }

    private function verifier(string ...$secrets): Verifier
    {
        return new Verifier(array_values($secrets), $this->clock);
    }

    public function testADeliverySignedLikeTheServerSignsItIsAcceptedAsATypedEvent(): void
    {
        $event = $this->verifier(self::SECRET)->verify(self::BODY, $this->headers([self::SECRET]));

        self::assertSame(self::ID, $event->id);
        self::assertSame('entitlement.granted', $event->type);
        self::assertSame('2026-10-08T11:59:58.123456+00:00', $event->timestamp->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(1, $event->version);
        self::assertSame('production', $event->mode);
        self::assertSame(['member' => 'm_1', 'url' => 'https://example.com/a/b', 'name' => 'Zoë'], $event->data);
    }

    public function testDuringARotationEitherSignatureInTheHeaderIsEnough(): void
    {
        $headers = $this->headers([self::OLD_SECRET, self::SECRET]);

        self::assertSame(self::ID, $this->verifier(self::SECRET)->verify(self::BODY, $headers)->id);
        self::assertSame(self::ID, $this->verifier(self::OLD_SECRET)->verify(self::BODY, $headers)->id);
    }

    public function testAnyOfSeveralConfiguredSecretsMayMatch(): void
    {
        $event = $this->verifier(self::OLD_SECRET, self::SECRET)->verify(self::BODY, $this->headers([self::SECRET]));

        self::assertSame(self::ID, $event->id);
    }

    public function testASignatureUnderAnotherSecretIsRefused(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('signature');

        $this->verifier(self::SECRET)->verify(self::BODY, $this->headers([self::OLD_SECRET]));
    }

    public function testATamperedBodyIsRefused(): void
    {
        $tampered = str_replace('m_1', 'm_2', self::BODY);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('signature');

        $this->verifier(self::SECRET)->verify($tampered, $this->headers([self::SECRET]));
    }

    public function testATamperedIdIsRefused(): void
    {
        $headers = $this->headers([self::SECRET]);
        $headers['webhook-id'] = 'msg_other';

        $this->expectException(WebhookVerificationException::class);

        $this->verifier(self::SECRET)->verify(self::BODY, $headers);
    }

    public function testATimestampJustInsideFiveMinutesIsAccepted(): void
    {
        $verifier = $this->verifier(self::SECRET);
        $now = $this->clock->timestamp();

        self::assertSame(self::ID, $verifier->verify(self::BODY, $this->headers([self::SECRET], $now - 300))->id);
        self::assertSame(self::ID, $verifier->verify(self::BODY, $this->headers([self::SECRET], $now + 300))->id);
    }

    public function testATimestampMoreThanFiveMinutesOldIsRefused(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('timestamp');

        $this->verifier(self::SECRET)->verify(self::BODY, $this->headers([self::SECRET], $this->clock->timestamp() - 301));
    }

    public function testATimestampMoreThanFiveMinutesAheadIsRefused(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('timestamp');

        $this->verifier(self::SECRET)->verify(self::BODY, $this->headers([self::SECRET], $this->clock->timestamp() + 301));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function headerNames(): iterable
    {
        yield 'webhook-id' => ['webhook-id'];
        yield 'webhook-timestamp' => ['webhook-timestamp'];
        yield 'webhook-signature' => ['webhook-signature'];
    }

    #[DataProvider('headerNames')]
    public function testAMissingHeaderIsRefused(string $name): void
    {
        $headers = $this->headers([self::SECRET]);
        unset($headers[$name]);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage($name);

        $this->verifier(self::SECRET)->verify(self::BODY, $headers);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedHeaders(): iterable
    {
        yield 'timestamp with letters' => ['webhook-timestamp', '17x'];
        yield 'negative timestamp' => ['webhook-timestamp', '-1'];
        yield 'timestamp in milliseconds' => ['webhook-timestamp', '1791460800000'];
        yield 'signature without a version' => ['webhook-signature', 'abc'];
        yield 'signature with an empty value' => ['webhook-signature', 'v1,'];
        yield 'signature in another scheme only' => ['webhook-signature', 'v1a,c2lnbmF0dXJl'];
        yield 'signature that is not base64' => ['webhook-signature', 'v1,%%%'];
        yield 'blank signature' => ['webhook-signature', '   '];
        yield 'blank id' => ['webhook-id', ''];
    }

    #[DataProvider('malformedHeaders')]
    public function testAMalformedHeaderIsATypedRefusalNotAWarning(string $name, string $value): void
    {
        $headers = $this->headers([self::SECRET]);
        $headers[$name] = $value;

        $this->expectException(WebhookVerificationException::class);

        $this->verifier(self::SECRET)->verify(self::BODY, $headers);
    }

    public function testEntriesInOtherSchemesAreSkippedWhileAV1EntryMatches(): void
    {
        $headers = $this->headers([self::SECRET]);
        $headers['webhook-signature'] = 'v1a,c2lnbmF0dXJl ' . $headers['webhook-signature'];

        self::assertSame(self::ID, $this->verifier(self::SECRET)->verify(self::BODY, $headers)->id);
    }

    public function testHeaderNamesAreMatchedWithoutRegardToCase(): void
    {
        $headers = [];
        foreach ($this->headers([self::SECRET]) as $name => $value) {
            $headers[ucwords($name, '-')] = [$value];
        }

        self::assertSame(self::ID, $this->verifier(self::SECRET)->verify(self::BODY, $headers)->id);
    }

    public function testAPsr7RequestIsVerifiedFromItsRawBody(): void
    {
        $request = new ServerRequest('POST', 'https://app.example.com/webhooks', $this->headers([self::SECRET]), self::BODY);

        self::assertSame(self::ID, $this->verifier(self::SECRET)->verifyRequest($request)->id);
    }

    public function testASignedBodyThatIsNotAnEnvelopeIsRefused(): void
    {
        $body = '{"id":"msg_1"}';

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('envelope');

        $this->verifier(self::SECRET)->verify($body, $this->headers([self::SECRET], null, $body));
    }

    public function testRefusalsAreAppsolutelyExceptions(): void
    {
        try {
            $this->verifier(self::SECRET)->verify(self::BODY, []);
            self::fail('No exception was thrown.');
        } catch (WebhookVerificationException $exception) {
            self::assertInstanceOf(AppsolutelyException::class, $exception);
        }
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function invalidSecrets(): iterable
    {
        yield 'no whsec_ prefix' => [['MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw']];
        yield 'not base64' => [['whsec_not*base64!']];
        yield 'empty after the prefix' => [['whsec_']];
        yield 'no secret at all' => [[]];
    }

    /**
     * @param list<string> $secrets
     */
    #[DataProvider('invalidSecrets')]
    public function testAnUnusableSecretIsRefusedAtConstruction(array $secrets): void
    {
        $this->expectException(InvalidSecretException::class);

        new Verifier($secrets, $this->clock);
    }

    public function testAnInvalidSecretIsNotEchoedInTheMessage(): void
    {
        try {
            new Verifier(['whsec_not*base64!'], $this->clock);
            self::fail('No exception was thrown.');
        } catch (InvalidSecretException $exception) {
            self::assertStringNotContainsString('not*base64', $exception->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Testing;

use Appsolutely\Sdk\Clock\SystemClock;
use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Webhooks\Secret;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;
use stdClass;

/**
 * Builds deliveries signed exactly as the Appsolutely server signs them, for
 * an integrator's own tests of their webhook endpoint. A test helper: not for
 * production code.
 */
final readonly class WebhookFactory
{
    /** The envelope's schema version the server writes. */
    public const int ENVELOPE_VERSION = 1;

    private const string CROCKFORD = '0123456789abcdefghjkmnpqrstvwxyz';

    /** @var non-empty-list<Secret> */
    private array $secrets;

    /**
     * @param list<string> $secrets one signature is added per secret, as during a rotation
     */
    public function __construct(
        #[\SensitiveParameter]
        array $secrets,
        private ClockInterface $clock = new SystemClock(),
    ) {
        $this->secrets = Secret::list($secrets);
    }

    /**
     * @param array<string, mixed> $data
     * @param string $mode `production` or `test`, as the server writes it
     * @param int|null $attemptedAt the `webhook-timestamp`, which is the attempt's time; now by default
     */
    public function make(
        string $type,
        array $data = [],
        string $mode = 'production',
        ?string $id = null,
        ?DateTimeImmutable $occurredAt = null,
        ?int $attemptedAt = null,
    ): SignedWebhook {
        $now = $this->clock->now();
        $id ??= 'msg_' . $this->ulid($now);
        $occurredAt ??= $now;

        $body = json_encode([
            'id' => $id,
            'type' => $type,
            'timestamp' => $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'version' => self::ENVELOPE_VERSION,
            'mode' => $mode,
            'data' => $data === [] ? new stdClass() : $data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $timestamp = $attemptedAt ?? $now->getTimestamp();

        return new SignedWebhook($id, $body, [
            Header::WEBHOOK_ID => $id,
            Header::WEBHOOK_TIMESTAMP => (string) $timestamp,
            Header::WEBHOOK_SIGNATURE => implode(' ', array_map(
                static fn(Secret $secret): string => 'v1,' . $secret->sign($id, $timestamp, $body),
                $this->secrets,
            )),
        ]);
    }

    /**
     * A lowercase ULID, the shape of the server's ids: 48 bits of
     * milliseconds and 80 random bits in Crockford Base32.
     */
    private function ulid(DateTimeImmutable $now): string
    {
        $milliseconds = (int) $now->format('Uv');
        $bytes = '';
        for ($shift = 40; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($milliseconds >> $shift) & 0xFF);
        }
        $bytes .= random_bytes(10);

        $bits = '00';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $ulid = '';
        foreach (str_split($bits, 5) as $chunk) {
            $ulid .= self::CROCKFORD[(int) bindec($chunk)];
        }

        return $ulid;
    }
}

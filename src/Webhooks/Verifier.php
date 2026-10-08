<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

use Appsolutely\Sdk\Clock\SystemClock;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\WebhookVerificationException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies a Standard Webhooks v1 delivery as Appsolutely signs it.
 *
 * Verify the raw body exactly as received: a body decoded and encoded again
 * differs in whitespace or escaping and no longer matches its signature.
 *
 * A duplicate `webhook-id` is not refused here. A retry and a redelivery
 * both reuse it, so refusing it would turn every retried delivery into a
 * failure; use Deliveries to skip the handler for an id already handled.
 */
final readonly class Verifier
{
    /** The window the Standard Webhooks specification recommends. */
    public const int DEFAULT_TOLERANCE = 300;

    /**
     * A wider window than an hour no longer protects against a replay, and
     * the signature already binds the timestamp, so clock drift is the only
     * reason to widen it at all.
     */
    public const int MAX_TOLERANCE = 3600;

    /** @var non-empty-list<Secret> */
    private array $secrets;

    /**
     * @param list<string> $secrets every secret currently valid; during a
     *                              rotation, the new one and the old one
     */
    public function __construct(
        #[\SensitiveParameter]
        array $secrets,
        private ClockInterface $clock = new SystemClock(),
        private int $tolerance = self::DEFAULT_TOLERANCE,
    ) {
        if ($tolerance < 1 || $tolerance > self::MAX_TOLERANCE) {
            throw new InvalidArgumentValueException(sprintf('The webhook timestamp tolerance must be between 1 and %d seconds, got %d.', self::MAX_TOLERANCE, $tolerance));
        }

        $this->secrets = Secret::list($secrets);
    }

    /**
     * @param array<string, string|array<string>> $headers header names in any case
     */
    public function verify(string $body, array $headers): Event
    {
        $normalised = [];
        foreach ($headers as $name => $value) {
            $normalised[strtolower($name)] = is_array($value) ? implode(' ', $value) : $value;
        }

        $id = $this->header($normalised, 'webhook-id');
        $timestamp = $this->timestamp($this->header($normalised, 'webhook-timestamp'));
        $signatures = $this->signatures($this->header($normalised, 'webhook-signature'));

        $matched = false;
        foreach ($this->secrets as $secret) {
            $expected = $secret->sign($id, $timestamp, $body);
            foreach ($signatures as $signature) {
                // Every pair is compared, never short-circuited on a match,
                // so the time taken does not tell which secret matched.
                $matched = hash_equals($expected, $signature) || $matched;
            }
        }

        if (!$matched) {
            throw new WebhookVerificationException('No v1 signature in webhook-signature matches the body under any configured secret.');
        }

        $event = Event::fromBody($body);
        // The server writes one occurrence id into both. A signed delivery
        // whose body names another id cannot be deduplicated on either, so
        // it is refused rather than handed to Deliveries under the wrong id.
        if ($event->id !== $id) {
            throw new WebhookVerificationException('The event id in the body is not the webhook-id header.');
        }

        return $event;
    }

    /**
     * Several values of one header are joined with spaces, the separator the
     * signature header uses, rather than the comma PSR-7 joins with, which
     * would run into the `v1,` prefix.
     */
    public function verifyRequest(ServerRequestInterface $request): Event
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $this->verify($body->getContents(), [
            'webhook-id' => $request->getHeader('webhook-id'),
            'webhook-timestamp' => $request->getHeader('webhook-timestamp'),
            'webhook-signature' => $request->getHeader('webhook-signature'),
        ]);
    }

    /**
     * @param array<string, string> $headers
     */
    private function header(array $headers, string $name): string
    {
        $value = trim($headers[$name] ?? '');
        if ($value === '') {
            throw new WebhookVerificationException(sprintf('The delivery has no %s header.', $name));
        }

        return $value;
    }

    private function timestamp(string $value): int
    {
        // Ten digits at most: whole seconds since the epoch until the year 2286.
        if (preg_match('/^\d{1,10}$/', $value) !== 1) {
            throw new WebhookVerificationException('The webhook-timestamp header is not a Unix time in seconds.');
        }

        $timestamp = (int) $value;
        if (abs($this->clock->now()->getTimestamp() - $timestamp) > $this->tolerance) {
            throw new WebhookVerificationException(sprintf('The webhook-timestamp is more than %d seconds from now; the delivery may be a replay.', $this->tolerance));
        }

        return $timestamp;
    }

    /**
     * @return non-empty-list<string>
     */
    private function signatures(string $header): array
    {
        $signatures = [];
        $entries = preg_split('/\s+/', $header, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($entries === false ? [] : $entries as $entry) {
            $parts = explode(',', $entry, 2);
            if (count($parts) === 2 && $parts[0] === 'v1' && $parts[1] !== '') {
                $signatures[] = $parts[1];
            }
        }

        if ($signatures === []) {
            throw new WebhookVerificationException('The webhook-signature header holds no v1 signature.');
        }

        return $signatures;
    }
}

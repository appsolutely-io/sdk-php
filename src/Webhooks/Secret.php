<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

use Appsolutely\Sdk\Exception\InvalidSecretException;
use Appsolutely\Sdk\Exception\NotSerializableException;

/**
 * A Standard Webhooks signing secret: `whsec_` followed by the Base64 of the
 * key bytes. The HMAC key is the decoded bytes, not the string.
 *
 * @internal
 */
final readonly class Secret
{
    public const string PREFIX = 'whsec_';

    /**
     * The Standard Webhooks specification: "Between 24 bytes (192 bits) and
     * 64 bytes (512 bits)". Shorter is guessable; longer is not a secret the
     * server issues (it issues 32 bytes), so it is a pasting mistake.
     */
    public const int MIN_BYTES = 24;
    public const int MAX_BYTES = 64;

    private function __construct(
        #[\SensitiveParameter]
        private string $key,
    ) {}

    /**
     * The secret itself never appears in an exception message: it would end
     * up in logs and error trackers.
     */
    public static function fromString(#[\SensitiveParameter] string $secret): self
    {
        if (!str_starts_with($secret, self::PREFIX)) {
            throw new InvalidSecretException('A webhook signing secret must start with "whsec_".');
        }

        $key = base64_decode(substr($secret, strlen(self::PREFIX)), true);
        if ($key === false || $key === '') {
            throw new InvalidSecretException('A webhook signing secret must be "whsec_" followed by non-empty Base64.');
        }

        if (strlen($key) < self::MIN_BYTES || strlen($key) > self::MAX_BYTES) {
            throw new InvalidSecretException(sprintf('A webhook signing secret must decode to between %d and %d bytes.', self::MIN_BYTES, self::MAX_BYTES));
        }

        return new self($key);
    }

    /**
     * Every secret currently valid, at least one: during a rotation, the new
     * one and the old one.
     *
     * @param list<string> $secrets
     * @return non-empty-list<self>
     */
    public static function list(#[\SensitiveParameter] array $secrets): array
    {
        $parsed = array_map(self::fromString(...), $secrets);
        if ($parsed === []) {
            throw new InvalidSecretException('At least one webhook signing secret is needed.');
        }

        return $parsed;
    }

    /**
     * What var_dump() and print_r() show, here and inside Verifier and
     * WebhookFactory, which hold secrets: the key is replaced, as Config
     * replaces the client secret. (var_export() ignores this hook.)
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['key' => '[redacted]'];
    }

    /**
     * Refused like Config's: a serialized copy would carry the key into
     * whatever store the string ends up in. Verifier and WebhookFactory are
     * refused through the secrets they hold.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new NotSerializableException('A webhook signing secret is not serialized; build the Verifier or WebhookFactory again from the secrets.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new NotSerializableException('A webhook signing secret is not unserialized; build the Verifier or WebhookFactory again from the secrets.');
    }

    /**
     * The `v1` signature: HMAC-SHA256 over `{id}.{timestamp}.{body}`.
     */
    public function sign(string $id, int $timestamp, string $body): string
    {
        return base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $body, $this->key, true));
    }
}

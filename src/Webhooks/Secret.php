<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

use Appsolutely\Sdk\Exception\InvalidSecretException;

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
     * The `v1` signature: HMAC-SHA256 over `{id}.{timestamp}.{body}`.
     */
    public function sign(string $id, int $timestamp, string $body): string
    {
        return base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $body, $this->key, true));
    }
}

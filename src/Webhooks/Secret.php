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

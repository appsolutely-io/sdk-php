<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Http\StructuredFieldList;

/**
 * The rate-limit budget an answer states, read from its `RateLimit-Policy`
 * and `RateLimit` fields (draft-ietf-httpapi-ratelimit-headers-11), joined
 * by quota name. A field that does not parse is ignored rather than failing
 * the call: the budget is advice, the answer is what was asked for.
 */
final readonly class RateLimit
{
    /**
     * @param array<string, RateLimitQuota> $quotas keyed by name, in the order the site named them
     */
    public function __construct(public array $quotas = []) {}

    public static function fromHeaders(string $policy, string $limit): self
    {
        /** @var array<string, array{quota?: int, window?: int, remaining?: int, reset?: int}> $numbers */
        $numbers = [];
        foreach ([[$policy, ['q' => 'quota', 'w' => 'window']], [$limit, ['r' => 'remaining', 't' => 'reset']]] as [$field, $names]) {
            foreach (StructuredFieldList::parse($field) ?? [] as [$name, $parameters]) {
                $numbers[$name] ??= [];
                foreach ($names as $parameter => $property) {
                    $value = $parameters[$parameter] ?? null;
                    if (is_int($value) && $value >= 0) {
                        $numbers[$name][$property] = $value;
                    }
                }
            }
        }

        $quotas = [];
        foreach ($numbers as $name => $values) {
            $quotas[$name] = new RateLimitQuota(
                $name,
                $values['quota'] ?? null,
                $values['window'] ?? null,
                $values['remaining'] ?? null,
                $values['reset'] ?? null,
            );
        }

        return new self($quotas);
    }

    public function quota(string $name): ?RateLimitQuota
    {
        return $this->quotas[$name] ?? null;
    }

    /**
     * Of the quotas with nothing left, the one whose window turns over last:
     * no request goes through before then.
     */
    public function exhausted(): ?RateLimitQuota
    {
        $exhausted = null;
        foreach ($this->quotas as $quota) {
            if ($quota->remaining === 0 && ($exhausted === null || ($quota->reset ?? 0) > ($exhausted->reset ?? 0))) {
                $exhausted = $quota;
            }
        }

        return $exhausted;
    }
}

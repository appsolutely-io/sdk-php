<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use DateTimeInterface;
use DateTimeZone;
use LogicException;

/**
 * Sends an Operation of the document with one audience's credential: the
 * path filled from its template, the method it names, and an
 * Idempotency-Key on every write that honours one. Every typed resource
 * method goes through here, so none of them builds a path or picks a verb
 * of its own.
 *
 * @internal
 */
final readonly class Caller
{
    public function __construct(
        private SiteApi $api,
        private Audience $audience,
    ) {}

    /**
     * @param array<string, string> $path the values of the path's placeholders
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query
     * @param array<string, mixed>|null $body
     */
    public function send(Operation $operation, array $path = [], #[\SensitiveParameter] array $query = [], #[\SensitiveParameter] ?array $body = null, ?string $idempotencyKey = null): ApiResponse
    {
        $this->assertAudience($operation);
        $url = $operation->path($path);
        $body = $body === null ? null : self::wire($body);
        $query = self::query($query);

        if ($operation->takesIdempotencyKey()) {
            return $this->api->postIdempotent($url, $body ?? [], $idempotencyKey);
        }
        if ($idempotencyKey !== null) {
            throw new LogicException(sprintf('%s takes no Idempotency-Key.', $operation->value));
        }

        return match ($operation->method()) {
            'GET' => $this->api->get($url, $query),
            'POST' => $this->api->post($url, $body ?? []),
            'PATCH' => $this->api->patch($url, $body ?? []),
            'PUT' => $this->api->put($url, $body ?? []),
            'DELETE' => $this->api->delete($url, $query),
            default => throw new LogicException(sprintf('%s uses %s, which the client does not send.', $operation->value, $operation->method())),
        };
    }

    /**
     * The JSON object an operation answered.
     *
     * @param array<string, string> $path
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function object(Operation $operation, array $path = [], #[\SensitiveParameter] array $query = [], #[\SensitiveParameter] ?array $body = null): array
    {
        return self::objectOf($this->send($operation, $path, $query, $body), $operation);
    }

    /**
     * Every item of a cursor list, a page at a time.
     *
     * @param array<string, string> $path
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query the list's filters
     * @return Paginator<array<string, mixed>>
     */
    public function paginate(Operation $operation, array $path, #[\SensitiveParameter] array $query, int $limit): Paginator
    {
        $this->assertAudience($operation);
        if (!$operation->isCursorList()) {
            throw new LogicException(sprintf('%s is not a cursor list.', $operation->value));
        }

        return $this->api->paginate($operation->path($path), self::query($query), $limit);
    }

    /**
     * A list the site answers as one page that never has a cursor.
     *
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query
     * @return Page<array<string, mixed>>
     */
    public function onlyPage(Operation $operation, #[\SensitiveParameter] array $query = []): Page
    {
        return Page::fromResponse($this->send($operation, [], $query), $operation->path());
    }

    /**
     * @return array<string, mixed>
     */
    public static function objectOf(ApiResponse $response, Operation $operation): array
    {
        $data = $response->data;
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new UnexpectedResponseException(sprintf('%s answered %d without a JSON object.', $operation->value, $response->status), $response->status);
        }

        /** @var array<string, mixed> $data the decoded answer's keys are made strings */
        return $data;
    }

    /**
     * A time as the site spells one, RFC 3339 in UTC to the second.
     */
    public static function time(DateTimeInterface $time): string
    {
        return \DateTimeImmutable::createFromInterface($time)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function assertAudience(Operation $operation): void
    {
        if ($operation->audience() !== $this->audience) {
            throw new LogicException(sprintf('%s is called by %s, not %s.', $operation->value, $operation->audience()->name, $this->audience->name));
        }
    }

    /**
     * A body with every time written as the site spells one.
     *
     * @template K of array-key
     *
     * @param array<K, mixed> $value
     * @return array<K, mixed>
     */
    private static function wire(#[\SensitiveParameter] array $value): array
    {
        foreach ($value as $key => $member) {
            if ($member instanceof DateTimeInterface) {
                $value[$key] = self::time($member);
            } elseif (is_array($member)) {
                $value[$key] = self::wire($member);
            }
        }

        return $value;
    }

    /**
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query
     * @return array<string, string|int|bool|list<string>|null>
     */
    private static function query(#[\SensitiveParameter] array $query): array
    {
        $wire = [];
        foreach ($query as $name => $value) {
            $wire[$name] = $value instanceof DateTimeInterface ? self::time($value) : $value;
        }

        return $wire;
    }
}

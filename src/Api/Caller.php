<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Model\Fields;
use Closure;
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
            // A keyed write is sent as postIdempotent() sends it, a POST with
            // a body and no query; anything else would go out without part of it.
            if ($operation->method() !== 'POST' || $query !== []) {
                throw new LogicException(sprintf('%s is a keyed write, which is sent as a POST without a query.', $operation->value));
            }

            return $this->api->postIdempotent($url, $body ?? [], $idempotencyKey);
        }
        if ($idempotencyKey !== null) {
            throw new LogicException(sprintf('%s takes no Idempotency-Key.', $operation->value));
        }

        return match ($operation->method()) {
            'GET' => $this->api->get($url, $query),
            'POST' => $this->api->post($url, $body ?? []),
            'PATCH' => $this->api->patch($url, $body ?? []),
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
     * @return array<array-key, mixed>
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
     * @return Paginator<array<array-key, mixed>>
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
     * A list the site answers as one page that never has a cursor, as models.
     *
     * @template T
     *
     * @param Closure(Fields): T $read
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query
     * @return Page<T>
     */
    public function onlyPage(Operation $operation, string $schema, Closure $read, #[\SensitiveParameter] array $query = []): Page
    {
        if ($operation->isCursorList()) {
            throw new LogicException(sprintf('%s is a cursor list.', $operation->value));
        }

        return Page::fromResponse($this->send($operation, [], $query), $operation->path())
            ->map(static fn(#[\SensitiveParameter] array $item): mixed => $read(Fields::of($item, $schema)));
    }

    /**
     * The model an operation answered, read from the schema the document
     * gives it.
     *
     * @template T
     *
     * @param Closure(Fields): T $read
     * @param array<string, string> $path
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query
     * @param array<string, mixed>|null $body
     * @return T
     */
    public function read(Operation $operation, string $schema, Closure $read, array $path = [], #[\SensitiveParameter] array $query = [], #[\SensitiveParameter] ?array $body = null): mixed
    {
        return $read(Fields::of($this->object($operation, $path, $query, $body), $schema));
    }

    /**
     * Every item of a cursor list as a model, a page at a time.
     *
     * @template T
     *
     * @param Closure(Fields): T $read
     * @param array<string, string|int|bool|DateTimeInterface|list<string>|null> $query the list's filters
     * @param array<string, string> $path
     * @return Paginator<T>
     */
    public function list(Operation $operation, string $schema, Closure $read, #[\SensitiveParameter] array $query, int $limit, array $path = []): Paginator
    {
        return $this->paginate($operation, $path, $query, $limit)->map(static fn(#[\SensitiveParameter] array $item): mixed => $read(Fields::of($item, $schema)));
    }

    /**
     * A write sent with an Idempotency-Key, and the model it answered.
     *
     * @template T
     *
     * @param Closure(Fields): T $read
     * @param array<string, string> $path
     * @param array<string, mixed> $body
     * @return KeyedResult<T>
     */
    public function keyed(Operation $operation, string $schema, Closure $read, array $path, #[\SensitiveParameter] array $body, ?string $idempotencyKey): KeyedResult
    {
        if (!$operation->takesIdempotencyKey()) {
            throw new LogicException(sprintf('%s takes no Idempotency-Key.', $operation->value));
        }

        $response = $this->send($operation, $path, [], $body, $idempotencyKey);

        return new KeyedResult(
            $read(Fields::of(self::objectOf($response, $operation), $schema)),
            (string) $response->idempotencyKey,
            $response->replayed,
            $response,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function objectOf(ApiResponse $response, Operation $operation): array
    {
        $data = $response->data;
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new UnexpectedResponseException(sprintf('%s answered %d without a JSON object.', $operation->value, $response->status), $response->status);
        }

        return $data;
    }

    /**
     * The query of a sync pull: the cursor to resume from, and a page size
     * the site bounds itself (100 by default, up to 500 unless the site is
     * configured otherwise), so only one below 1 is refused here.
     *
     * @return array{cursor: string|null, limit: int|null}
     */
    public static function feed(?string $cursor, ?int $limit): array
    {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentValueException(sprintf('A sync pull\'s limit must be at least 1, got %d.', $limit));
        }

        return ['cursor' => $cursor, 'limit' => $limit];
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

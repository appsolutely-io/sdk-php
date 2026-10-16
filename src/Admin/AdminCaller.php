<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin;

use Appsolutely\Sdk\Admin\Model\AdminModel;
use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Model\Fields;
use LogicException;

/**
 * Sends an AdminOperation and reads its answer as the model it names.
 *
 * @internal used by the Administrator API resources
 */
final readonly class AdminCaller
{
    public function __construct(private SiteApi $api) {}

    /**
     * @template T of AdminModel
     *
     * @param class-string<T> $model
     * @param array<string, string|null> $query the list's sort and order
     * @return Paginator<T>
     */
    public function list(AdminOperation $operation, string $model, array $query, int $limit): Paginator
    {
        self::assertIs($operation->isList(), $operation, 'a list');

        return $this->api->paginate($operation->path(), $query, $limit)
            ->map(static fn(#[\SensitiveParameter] array $item): AdminModel => $model::from(Fields::of($item, $model::SCHEMA)));
    }

    /**
     * @template T of AdminModel
     *
     * @param class-string<T> $model
     * @param array<string, string> $path
     * @return Versioned<T>
     */
    public function read(AdminOperation $operation, string $model, array $path): Versioned
    {
        self::assertIs($operation->method() === 'GET' && !$operation->isList(), $operation, 'a read');

        return self::versioned($this->api->get($operation->path($path)), $operation, $model);
    }

    /**
     * @template T of AdminModel
     *
     * @param class-string<T> $model
     * @param array<string, string> $path
     * @param array<string, mixed> $body
     * @return KeyedResult<T>
     */
    public function keyed(AdminOperation $operation, string $model, array $path, #[\SensitiveParameter] array $body, ?string $idempotencyKey): KeyedResult
    {
        self::assertIs($operation->takesIdempotencyKey(), $operation, 'a keyed write');
        $response = $this->api->postIdempotent($operation->path($path), $body, $idempotencyKey);

        return new KeyedResult($model::from(Fields::of(self::objectOf($response, $operation), $model::SCHEMA)), (string) $response->idempotencyKey, $response->replayed, $response);
    }

    /**
     * A keyed action answered with an object that names no schema.
     *
     * @param array<string, string> $path
     * @param array<string, mixed> $body
     * @return KeyedResult<array<array-key, mixed>>
     */
    public function keyedAction(AdminOperation $operation, array $path, #[\SensitiveParameter] array $body, ?string $idempotencyKey): KeyedResult
    {
        self::assertIs($operation->takesIdempotencyKey(), $operation, 'a keyed write');
        $response = $this->api->postIdempotent($operation->path($path), $body, $idempotencyKey);

        return new KeyedResult(self::objectOf($response, $operation), (string) $response->idempotencyKey, $response->replayed, $response);
    }

    /**
     * @template T of AdminModel
     *
     * @param class-string<T> $model
     * @param array<string, string> $path
     * @param array<string, mixed> $body
     * @return Versioned<T>
     */
    public function update(AdminOperation $operation, string $model, array $path, string $etag, #[\SensitiveParameter] array $body): Versioned
    {
        self::assertIs($operation->method() === 'PATCH' && $operation->requiresIfMatch(), $operation, 'an update over a version');

        return self::versioned($this->api->patch($operation->path($path), $body, ifMatch: self::etag($etag)), $operation, $model);
    }

    /**
     * @param array<string, string> $path
     */
    public function delete(AdminOperation $operation, array $path, string $etag): ApiResponse
    {
        self::assertIs($operation->method() === 'DELETE' && $operation->requiresIfMatch(), $operation, 'a delete over a version');

        return $this->api->delete($operation->path($path), ifMatch: self::etag($etag));
    }

    /**
     * @template T of AdminModel
     *
     * @param class-string<T> $model
     * @return Versioned<T>
     */
    private static function versioned(ApiResponse $response, AdminOperation $operation, string $model): Versioned
    {
        return new Versioned($model::from(Fields::of(self::objectOf($response, $operation), $model::SCHEMA)), $response->etag(), $response);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function objectOf(ApiResponse $response, AdminOperation $operation): array
    {
        $data = $response->data;
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new UnexpectedResponseException(sprintf('%s answered %d without a JSON object.', $operation->value, $response->status), $response->status);
        }

        return $data;
    }

    private static function etag(string $etag): string
    {
        if ($etag === '') {
            throw new InvalidArgumentValueException('An update or a delete names the version it was read at: pass the ETag a read answered.');
        }

        return $etag;
    }

    private static function assertIs(bool $is, AdminOperation $operation, string $what): void
    {
        if (!$is) {
            throw new LogicException(sprintf('%s is not %s.', $operation->value, $what));
        }
    }
}

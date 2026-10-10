<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Support\Untrusted;
use Closure;

/**
 * One page of a Site API list: its items and the cursor that asks for the
 * next page. The cursor is the site's, sealed and opaque; send it back
 * unchanged and build nothing on its contents.
 *
 * @template-covariant T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     * @param string|null $nextCursor absent on the last page
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public ApiResponse $response,
    ) {}

    /**
     * The page a GET answered, as `{data, next_cursor}` with every item an
     * object; an empty `next_cursor` is read as none.
     *
     * @internal
     *
     * @return self<array<string, mixed>>
     */
    public static function fromResponse(ApiResponse $response, string $path): self
    {
        $data = $response->data;
        $items = is_array($data) && !array_is_list($data) ? ($data['data'] ?? null) : null;
        $next = is_array($data) ? ($data['next_cursor'] ?? null) : null;

        if (!is_array($items) || !array_is_list($items) || ($next !== null && !is_string($next))) {
            throw new UnexpectedResponseException(sprintf('GET %s did not answer a page of the form {data, next_cursor}.', Untrusted::text($path, Untrusted::MAX_LONG_LENGTH)), $response->status);
        }

        $objects = [];
        foreach ($items as $item) {
            if (!is_array($item) || ($item !== [] && array_is_list($item))) {
                throw new UnexpectedResponseException(sprintf('GET %s answered a page whose items are not all objects.', Untrusted::text($path, Untrusted::MAX_LONG_LENGTH)), $response->status);
            }
            $objects[] = Json::stringKeys($item);
        }

        return new self($objects, $next === '' ? null : $next, $response);
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * @template U
     *
     * @param Closure(T): U $map
     * @return Page<U>
     */
    public function map(Closure $map): self
    {
        return new self(array_map($map, $this->items), $this->nextCursor, $this->response);
    }
}

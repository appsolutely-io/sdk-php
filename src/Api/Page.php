<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

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

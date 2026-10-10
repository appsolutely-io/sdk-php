<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Closure;
use Generator;
use IteratorAggregate;

/**
 * Every item of a Site API list, fetched a page at a time: a page is asked
 * for only when iteration reaches it, with the previous page's cursor
 * unchanged, until a page carries none. Iterating again starts again from
 * the first page.
 *
 * @template-covariant T
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class Paginator implements IteratorAggregate
{
    /**
     * @internal obtain it from SiteApi::paginate() or a resource's list()
     *
     * @param Closure(string|null): Page<T> $fetch
     */
    public function __construct(private Closure $fetch) {}

    /**
     * @return Generator<int, T>
     */
    public function getIterator(): Generator
    {
        foreach ($this->pages() as $page) {
            yield from $page->items;
        }
    }

    /**
     * One page: the first, or the one a cursor this list issued (a page's
     * nextCursor, kept from an earlier run) asks for. One request.
     *
     * @return Page<T>
     */
    public function page(?string $cursor = null): Page
    {
        return ($this->fetch)($cursor);
    }

    /**
     * The pages themselves, for a caller that wants each page's answer or
     * cursor.
     *
     * @return Generator<int, Page<T>>
     */
    public function pages(): Generator
    {
        $cursor = null;
        // Every cursor followed in this walk: a next cursor among them leads
        // back to a page already given, and following it would never end.
        $followed = [];
        do {
            $page = ($this->fetch)($cursor);
            if ($page->nextCursor !== null && isset($followed[$page->nextCursor])) {
                throw new UnexpectedResponseException('The site answered a page whose next cursor leads back to a page already given; following it would never end.', $page->response->status);
            }
            yield $page;
            $cursor = $page->nextCursor;
            if ($cursor !== null) {
                $followed[$cursor] = true;
            }
        } while ($cursor !== null);
    }

    /**
     * The same list with each item converted as it is reached.
     *
     * @template U
     *
     * @param Closure(T): U $map
     * @return self<U>
     */
    public function map(Closure $map): self
    {
        $fetch = $this->fetch;

        return new self(static fn(?string $cursor): Page => $fetch($cursor)->map($map));
    }
}

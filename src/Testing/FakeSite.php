<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Testing;

use Closure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The PSR-18 client a FakeClient's Client sends through: every request is
 * answered in memory.
 *
 * @internal
 */
final readonly class FakeSite implements ClientInterface
{
    /**
     * @param Closure(RequestInterface): ResponseInterface $answer
     */
    public function __construct(private Closure $answer) {}

    public function sendRequest(#[\SensitiveParameter] RequestInterface $request): ResponseInterface
    {
        return ($this->answer)($request);
    }
}

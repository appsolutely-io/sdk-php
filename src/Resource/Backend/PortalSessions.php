<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\PortalSession;

/**
 * Visits to the site's billing pages the backend opens for a member it
 * names.
 */
final readonly class PortalSessions
{
    /** @internal obtain it from Client::backend()->portalSessions() */
    public function __construct(private Caller $caller) {}

    /**
     * Opens a visit once, however often the call is retried; a retry hands
     * out a fresh address for the same visit.
     *
     * @param string $subject the member's public name, the `sub` its ID token carries
     * @param string $returnUrl where the member is sent back to: one of the backend's return targets
     * @return KeyedResult<PortalSession>
     */
    #[Endpoint(Operation::CreatePortalSession)]
    public function create(string $subject, string $returnUrl, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreatePortalSession, PortalSession::class, [], ['subject' => $subject, 'return_url' => $returnUrl], $idempotencyKey);
    }
}

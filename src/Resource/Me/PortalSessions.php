<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\PortalSession;

/**
 * Visits to the site's billing pages the member opens.
 */
final readonly class PortalSessions
{
    /** @internal obtain it from Client::forMember()->api()->me()->portalSessions() */
    public function __construct(private Caller $caller) {}

    /**
     * Opens a visit once, however often the call is retried; a retry hands
     * out a fresh address for the same visit.
     *
     * @param string $returnUrl where the member is sent back to: one of the app's return targets
     * @return KeyedResult<PortalSession>
     */
    #[Endpoint(Operation::CreateMePortalSession)]
    public function create(string $returnUrl, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateMePortalSession, PortalSession::class, [], ['return_url' => $returnUrl], $idempotencyKey);
    }
}

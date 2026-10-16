<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Fulfilment;

/**
 * Digital deliveries the site's backend fulfils: the content a buyer
 * receives for a pending delivery token.
 */
final readonly class Fulfilments
{
    /** @internal obtain it from Client::backend()->fulfilments() */
    public function __construct(private Caller $caller) {}

    /**
     * Fulfils a pending delivery once, however often the call is retried; a
     * retry with the same key answers the first outcome. A token no longer
     * pending (delivered, failed or expired) is refused as a
     * ConflictException of type `conflict`.
     *
     * @param string $token the delivery token, as the order's delivery named it
     * @param string $deliveryPayload what the buyer receives, such as a licence key or a download link
     * @param string|null $channel how it was delivered, for the record
     * @return KeyedResult<Fulfilment>
     */
    #[Endpoint(Operation::CreateFulfilment)]
    public function create(
        #[\SensitiveParameter]
        string $token,
        #[\SensitiveParameter]
        string $deliveryPayload,
        ?string $channel = null,
        ?string $idempotencyKey = null,
    ): KeyedResult {
        return $this->caller->keyed(Operation::CreateFulfilment, Fulfilment::class, [], [
            'token' => $token,
            'delivery_payload' => $deliveryPayload,
            'channel' => $channel,
        ], $idempotencyKey);
    }
}

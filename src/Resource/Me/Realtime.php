<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\ConditionalResult;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\RealtimeBootstrap;
use Appsolutely\Sdk\Model\RealtimeBroadcastAuth;
use Appsolutely\Sdk\Model\RealtimeDigest;
use Appsolutely\Sdk\Model\RealtimeEvents;
use Appsolutely\Sdk\Model\RealtimeHubToken;

final readonly class Realtime
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetMeRealtime)]
    public function get(): RealtimeBootstrap
    {
        return $this->caller->read(Operation::GetMeRealtime, RealtimeBootstrap::class);
    }

    #[Endpoint(Operation::BroadcastingAuthMeRealtime)]
    public function broadcastingAuth(string $channelName, string $socketId): RealtimeBroadcastAuth
    {
        return $this->caller->read(Operation::BroadcastingAuthMeRealtime, RealtimeBroadcastAuth::class, body: ['channel_name' => $channelName, 'socket_id' => $socketId]);
    }

    #[Endpoint(Operation::DigestMeRealtime)]
    public function digest(): RealtimeDigest
    {
        return $this->caller->read(Operation::DigestMeRealtime, RealtimeDigest::class);
    }

    /** @return ConditionalResult<RealtimeDigest> */
    public function digestConditional(string $ifNoneMatch): ConditionalResult
    {
        return $this->caller->conditionalRead(Operation::DigestMeRealtime, RealtimeDigest::class, $ifNoneMatch);
    }

    #[Endpoint(Operation::EventsMeRealtime)]
    public function events(int $after): RealtimeEvents
    {
        return $this->caller->read(Operation::EventsMeRealtime, RealtimeEvents::class, query: ['after' => $after]);
    }

    #[Endpoint(Operation::HubTokenMeRealtime)]
    public function hubToken(): RealtimeHubToken
    {
        return $this->caller->read(Operation::HubTokenMeRealtime, RealtimeHubToken::class);
    }
}

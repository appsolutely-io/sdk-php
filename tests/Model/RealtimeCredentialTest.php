<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Model;

use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\RealtimeBroadcastAuth;
use Appsolutely\Sdk\Model\RealtimeHubToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RealtimeCredentialTest extends TestCase
{
    /** @return iterable<string, array{RealtimeBroadcastAuth|RealtimeHubToken}> */
    public static function credentials(): iterable
    {
        yield 'broadcast authentication' => [RealtimeBroadcastAuth::from(Fields::of(['auth' => 'synthetic-sensitive-auth'], RealtimeBroadcastAuth::SCHEMA))];
        yield 'hub token' => [RealtimeHubToken::from(Fields::of(['url' => 'https://hub.example', 'topics' => [], 'expires_in' => 60, 'token_type' => 'Bearer', 'access_token' => 'synthetic-sensitive-token'], RealtimeHubToken::SCHEMA))];
    }

    #[DataProvider('credentials')]
    public function testDebugOutputHidesCredentials(RealtimeBroadcastAuth|RealtimeHubToken $credential): void
    {
        self::assertStringNotContainsString('synthetic-sensitive', print_r($credential, true));
    }

    #[DataProvider('credentials')]
    public function testCredentialHoldersRefuseSerialization(RealtimeBroadcastAuth|RealtimeHubToken $credential): void
    {
        $this->expectException(NotSerializableException::class);
        serialize($credential);
    }
}

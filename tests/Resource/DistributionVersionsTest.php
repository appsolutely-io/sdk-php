<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class DistributionVersionsTest extends TestCase
{
    public function testVersionTargetsAndChannelsAreForwardedAsSegments(): void
    {
        $fake = (new FakeClient())
            ->answer(Operation::from('getDistributionVersion'), ['distribution' => 'desktop', 'min_supported_version' => null])
            ->answer(Operation::from('getDistributionTargetVersion'), ['distribution' => 'desktop', 'min_supported_version' => '2.3.4']);
        $versions = $fake->api()->distributionVersions();
        self::assertNull($versions->get('desk/top')->minSupportedVersion);
        self::assertSame('2.3.4', $versions->target('desktop', 'mac/arm', 'beta')->minSupportedVersion);
        $call = $fake->lastCall(Operation::from('getDistributionTargetVersion'));
        self::assertSame(['distribution' => 'desktop', 'target' => 'mac/arm'], $call->pathParameters);
        self::assertSame(['channel' => 'beta'], $call->query);
        self::assertNull($call->token);
    }

    public function testATargetSendsTheRunningAndSystemVersionsWhenGiven(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetDistributionTargetVersion, ['distribution' => 'desktop', 'min_supported_version' => '2.0.0']);

        $fake->api()->distributionVersions()->target('desktop', 'macos-arm64', currentVersion: '1.9.0', osVersion: '14.5');

        self::assertSame(['current_version' => '1.9.0', 'os_version' => '14.5'], $fake->lastCall(Operation::GetDistributionTargetVersion)->query);
    }

    public function testATauriUpdateIsReadAsItsManifest(): void
    {
        $fake = (new FakeClient())->answer(Operation::GetTauriUpdate, ['version' => '2.0.0', 'notes' => null, 'pub_date' => '2026-11-01T00:00:00Z', 'url' => 'https://downloads.example.test/app.tar.gz', 'signature' => 'sig', 'minimum_system_version' => '13.0']);

        $update = $fake->api()->distributionVersions()->tauriUpdate('desktop', 'darwin-aarch64', '1.9.0', channel: 'beta', osVersion: '14.5');

        self::assertNotNull($update);
        self::assertSame('2.0.0', $update->version);
        self::assertSame('13.0', $update->minimumSystemVersion);
        $call = $fake->lastCall(Operation::GetTauriUpdate);
        self::assertSame(['distribution' => 'desktop', 'target' => 'darwin-aarch64', 'version' => '1.9.0'], $call->pathParameters);
        self::assertSame(['channel' => 'beta', 'os_version' => '14.5'], $call->query);
        self::assertNull($call->token);
    }

    public function testNoTauriUpdateIsNull(): void
    {
        $site = (new SiteRecorder())->empty(204);

        self::assertNull($site->provider->client()->api()->distributionVersions()->tauriUpdate('desktop', 'darwin-aarch64', '2.0.0'));
        self::assertSame('GET /api/tauri/updates/desktop/darwin-aarch64/2.0.0', $site->line());
    }
}

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
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\DistributionVersion;

/**
 * Minimum supported distribution versions and release-channel targets.
 */
final readonly class DistributionVersions
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetDistributionVersion)]
    public function get(string $distribution): DistributionVersion
    {
        return $this->caller->read(Operation::GetDistributionVersion, DistributionVersion::class, ['distribution' => $distribution]);
    }

    #[Endpoint(Operation::GetDistributionTargetVersion)]
    public function target(string $distribution, string $target, ?string $channel = null): DistributionVersion
    {
        return $this->caller->read(Operation::GetDistributionTargetVersion, DistributionVersion::class, ['distribution' => $distribution, 'target' => $target], ['channel' => $channel]);
    }
}

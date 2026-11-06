<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\DistributionVersion;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\TauriUpdateManifest;

/**
 * Minimum supported distribution versions, release-channel targets and
 * the updates a Tauri desktop app is offered.
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

    /**
     * @param string|null $currentVersion the version the app runs now, so the site can answer for an upgrade from it
     * @param string|null $osVersion the system version the app runs on, in the target platform's numeric format (`13.0` on macOS)
     */
    #[Endpoint(Operation::GetDistributionTargetVersion)]
    public function target(string $distribution, string $target, ?string $channel = null, ?string $currentVersion = null, ?string $osVersion = null): DistributionVersion
    {
        return $this->caller->read(Operation::GetDistributionTargetVersion, DistributionVersion::class, ['distribution' => $distribution, 'target' => $target], ['channel' => $channel, 'current_version' => $currentVersion, 'os_version' => $osVersion]);
    }

    /**
     * The update a Tauri desktop app at `$version` is offered, as the Tauri
     * updater reads it, or null when it is up to date (the site answers 204).
     *
     * @param string|null $osVersion the system version the app runs on, in the target platform's numeric format (`13.0` on macOS)
     */
    #[Endpoint(Operation::GetTauriUpdate)]
    public function tauriUpdate(string $distribution, string $target, string $version, ?string $channel = null, ?string $osVersion = null): ?TauriUpdateManifest
    {
        $response = $this->caller->send(Operation::GetTauriUpdate, ['distribution' => $distribution, 'target' => $target, 'version' => $version], ['channel' => $channel, 'os_version' => $osVersion]);
        if ($response->status === 204) {
            return null;
        }

        return TauriUpdateManifest::from(Fields::of(Caller::objectOf($response, Operation::GetTauriUpdate), TauriUpdateManifest::SCHEMA));
    }
}

<?php

namespace App\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;

/**
 * Drop-in replacement for Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper.
 *
 * Bug: the vendor revert() (vendor/stancl/tenancy/src/Bootstrappers/FilesystemTenancyBootstrapper.php)
 * does `$this->originalPaths['disks'][$disk]` with no isset()/?? guard. That's only safe if every
 * revert() is paired with a prior bootstrap() call on the SAME bootstrapper instance that populated
 * originalPaths['disks'][$disk]. Stancl's own Tenancy::initialize() has a standing TODO acknowledging
 * this isn't guaranteed ("Remove this ... make the FS bootstrapper work either way") — initialize()
 * calls end() first if tenancy is already initialized, which can fire revert() without a matching
 * bootstrap() on this instance. In practice this throws "Undefined array key" for any disk the moment
 * a single request/command visits more than one tenant (e.g. any artisan command that loops tenants
 * with $tenant->run() — including PromoteSportsHeadsToDisciplineEventsCommand).
 *
 * Fix: only revert a disk's root if bootstrap() actually recorded an original value for it. If not,
 * there's nothing to restore — leave it alone rather than crashing the whole command.
 */
class SafeFilesystemTenancyBootstrapper extends FilesystemTenancyBootstrapper
{
    public function revert()
    {
        // storage_path()
        $this->app->useStoragePath($this->originalPaths['storage']);

        // asset()
        $this->app['config']['app.asset_url'] = $this->originalPaths['asset_url'];
        $this->app['url']->setAssetRoot($this->app['config']['app.asset_url']);

        // Storage facade
        Storage::forgetDisk($this->app['config']['tenancy.filesystem.disks']);
        foreach ($this->app['config']['tenancy.filesystem.disks'] as $disk) {
            if (! array_key_exists($disk, $this->originalPaths['disks'] ?? [])) {
                continue;
            }

            $this->app['config']["filesystems.disks.{$disk}.root"] = $this->originalPaths['disks'][$disk];
        }
    }
}

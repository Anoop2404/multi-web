<?php

namespace App\Console\Commands;

use App\Support\TenantDomainSync;
use Illuminate\Console\Command;

class SyncTenantDomains extends Command
{
    protected $signature = 'tenants:sync-domains';

    protected $description = 'Sync tenant domain/subdomain fields into the Stancl domains table';

    public function handle(): int
    {
        $count = TenantDomainSync::syncAll();

        $this->info("Synced domains for {$count} tenant(s).");

        return self::SUCCESS;
    }
}

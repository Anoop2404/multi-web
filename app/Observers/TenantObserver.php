<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Support\TenantDomainSync;

class TenantObserver
{
    public function creating(Tenant $tenant): void
    {
        if ($tenant->type === 'sahodaya') {
            $tenant->setInternal('create_database', false);

            if (config('tenancy.database_per_sahodaya', true) && $tenant->getTenantKey()) {
                $tenant->setInternal('db_name', config('tenancy.database.prefix')
                    .str_replace('-', '_', $tenant->getTenantKey()));
            }
        }

        if ($tenant->type === 'school' && $tenant->parent_id) {
            $parent = Tenant::query()->find($tenant->parent_id);

            if ($parent?->type === 'sahodaya') {
                $parent->database()->makeCredentials();
                $tenant->setInternal('create_database', false);
                $tenant->setInternal('db_name', $parent->database()->getName());
            }
        }
    }

    public function saved(Tenant $tenant): void
    {
        TenantDomainSync::sync($tenant);
    }

    public function deleted(Tenant $tenant): void
    {
        $tenant->domains()->delete();
    }
}

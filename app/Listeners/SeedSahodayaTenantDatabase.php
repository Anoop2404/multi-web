<?php

namespace App\Listeners;

use App\Models\SahodayaProfile;
use App\Services\Events\FestCatalogService;
use App\Support\SahodayaSiteTemplate;
use Stancl\Tenancy\Events\DatabaseMigrated;

class SeedSahodayaTenantDatabase
{
    public function handle(DatabaseMigrated $event): void
    {
        $tenant = $event->tenant;

        if ($tenant->type !== 'sahodaya') {
            return;
        }

        $tenant->run(function () use ($tenant) {
            SahodayaProfile::firstOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'student_data_mode'   => 'not_required',
                    'membership_fee_type' => 'fixed',
                ]
            );

            if ($tenant->sections()->count() === 0) {
                SahodayaSiteTemplate::apply($tenant);
            }

            app(FestCatalogService::class)->ensureSeeded($tenant->id, 'sports');
            app(FestCatalogService::class)->ensureSeeded($tenant->id, 'kalolsavam');
            app(FestCatalogService::class)->ensureSeeded($tenant->id, 'kids_fest');
            app(FestCatalogService::class)->ensureSeeded($tenant->id, 'teacher_fest');
            app(FestCatalogService::class)->ensureSeeded($tenant->id, 'english_fest');
            app(FestCatalogService::class)->ensureSeeded($tenant->id, 'science_fest');

            app(\App\Services\Events\FestCompetitionTypeRegistry::class)
                ->forTenant($tenant->id)
                ->ensureDefaults();
            app(\App\Services\Events\FestTaxonomyRegistry::class)
                ->forTenant($tenant->id)
                ->ensureDefaults();
        });
    }
}

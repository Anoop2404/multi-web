<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\StandaloneSahodayaImporter;
use App\Support\CkscSiteTemplate;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class ApplyCkscSiteTemplate extends Command
{
    protected $signature = 'sahodaya:apply-cksc
                            {tenant : Tenant UUID or slug}
                            {--source= : Path to standalone sahodaya project for DB import}
                            {--keep-sections : Do not replace existing homepage sections}';

    protected $description = 'Apply CKSC standalone website layout (pill nav, hero slider, sections, CMS pages) to a Sahodaya tenant';

    public function handle(): int
    {
        $tenant = Tenant::query()
            ->where('id', $this->argument('tenant'))
            ->orWhere('subdomain', $this->argument('tenant'))
            ->first();

        if (! $tenant || $tenant->type !== 'sahodaya') {
            $this->error('Sahodaya tenant not found.');

            return self::FAILURE;
        }

        $replaceSections = ! $this->option('keep-sections');
        $source = $this->option('source');

        TenancyDatabase::runWhenDatabaseReady($tenant, function () use ($tenant, $replaceSections, $source) {
            if ($source) {
                try {
                    $stats = (new StandaloneSahodayaImporter($source))->importInto($tenant);
                    $this->info("Imported content from {$source}:");
                    foreach ($stats as $key => $value) {
                        $this->line("  - {$key}: ".(is_bool($value) ? ($value ? 'yes' : 'no') : $value));
                    }
                } catch (\Throwable $e) {
                    $this->warn('DB import failed: '.$e->getMessage());
                    $this->warn('Applying built-in CKSC template defaults instead…');
                    CkscSiteTemplate::apply($tenant, $replaceSections);
                }
            } else {
                CkscSiteTemplate::apply($tenant, $replaceSections);
                $this->info('Applied built-in CKSC template (no --source DB import).');
            }
        });

        $tenant->invalidateCache();

        $this->info("CKSC website template applied to: {$tenant->name} ({$tenant->id})");
        $this->line('Nav: cksc-pill | Hero: cksc-slider | Theme: tenant colours from profile/subdomain');
        $this->line('Edit sections in Sahodaya Site Builder, CMS pages in tenant settings.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnifyFestEventTypes extends Command
{
    protected $signature = 'fest:unify-event-types';

    protected $description = 'Unify all variations of kalotsav/kalolsavam and sports event_types to canonical names across central and tenant databases';

    public function handle(): int
    {
        $this->info('Unifying central database event_types...');

        $updatedCentralPrograms = DB::table('fest_state_programs')
            ->whereIn('event_type', ['kalotsav', 'art_fest', 'co_curricular'])
            ->update(['event_type' => 'kalolsavam']);

        $updatedCentralSports = DB::table('fest_state_programs')
            ->whereIn('event_type', ['sports_meet', 'athletics'])
            ->update(['event_type' => 'sports']);

        $this->line("  Central fest_state_programs: {$updatedCentralPrograms} kalolsavam, {$updatedCentralSports} sports updated.");

        $tenants = Tenant::where('type', 'sahodaya')->get();
        $this->info("Unifying tenant databases for {$tenants->count()} Sahodaya complex(es)...");

        foreach ($tenants as $tenant) {
            try {
                TenancyDatabase::runWhenDatabaseReady($tenant, function () use ($tenant) {
                    $eventsCount = 0;
                    $catalogCount = 0;

                    if (Schema::hasTable('fest_events')) {
                        $eventsCount += DB::table('fest_events')
                            ->whereIn('event_type', ['kalotsav', 'art_fest', 'co_curricular'])
                            ->update(['event_type' => 'kalolsavam']);
                        $eventsCount += DB::table('fest_events')
                            ->whereIn('event_type', ['sports_meet', 'athletics'])
                            ->update(['event_type' => 'sports']);
                    }

                    if (Schema::hasTable('fest_catalog_items')) {
                        $catalogCount += DB::table('fest_catalog_items')
                            ->whereIn('event_type', ['kalotsav', 'art_fest', 'co_curricular'])
                            ->update(['event_type' => 'kalolsavam']);
                        $catalogCount += DB::table('fest_catalog_items')
                            ->whereIn('event_type', ['sports_meet', 'athletics'])
                            ->update(['event_type' => 'sports']);
                    }

                    $this->line("  Tenant [{$tenant->id}]: {$eventsCount} event(s) and {$catalogCount} catalog item(s) updated.");
                });
            } catch (\Throwable $e) {
                $this->error("  Tenant [{$tenant->id}] failed: {$e->getMessage()}");
            }
        }

        $this->info('Event type unification complete.');

        return self::SUCCESS;
    }
}

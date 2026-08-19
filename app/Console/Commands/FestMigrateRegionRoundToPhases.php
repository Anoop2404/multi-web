<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestRegionRoundMigrationService;
use Illuminate\Console\Command;

/**
 * Branch B: converts an event's existing region-partition round (real schools already
 * registered against real items) into the phased-regional-billing structure, without
 * losing any of that data. See app/Services/Events/FestRegionRoundMigrationService.php
 * for the adopt-in-place / move-merge design.
 *
 * Always run fest:audit-region-conversion-readiness first to confirm which children are
 * legacy region partitions and how much data they carry. The config file (same shape as
 * fest:configure-phased-structure's, plus a required 'legacy_adoption' map of
 * {legacy_child_event_id: phase_code}) must reflect the REAL item→phase categorization —
 * there is no placeholder split for a run against real registrations.
 *
 * Dry-run (default) previews every batch/phase/item change and the full adoption plan
 * (which items stay, which move, registration/participant counts per item) without writing
 * anything. --commit requires every enabled item to be mapped, wraps the whole migration in
 * one transaction, and is safe to re-run only in the sense that a failed/rolled-back attempt
 * leaves nothing changed — it is NOT safe to run twice successfully (the second run would
 * see legacy children already adopted and fail closer at the legacyPartitions() check).
 */
class FestMigrateRegionRoundToPhases extends Command
{
    protected $signature = 'fest:migrate-region-round-to-phases
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Root fest_events id to migrate (required)}
        {--config= : Path to a PHP config file returning the plan array, including legacy_adoption (required, no default)}
        {--commit : Persist the migration and enable phased_regional_billing (defaults to dry-run)}';

    protected $description = 'Migrate an event\'s existing region-partition round (with real registrations) into the phased-regional-billing structure';

    public function handle(FestRegionRoundMigrationService $migrator): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $configOpt = $this->option('config');
        $commit = (bool) $this->option('commit');

        if (! $sahodayaOpt || ! $eventOpt || ! $configOpt) {
            $this->error('--sahodaya, --event, and --config are all required.');

            return self::FAILURE;
        }

        if (! is_file($configOpt)) {
            $this->error("Config file not found: {$configOpt}");

            return self::FAILURE;
        }
        $config = require $configOpt;
        if (! is_array($config)) {
            $this->error("Config file must return an array: {$configOpt}");

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('type', 'sahodaya')
            ->where(function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            })
            ->first();

        if (! $tenant) {
            $this->error("No matching Sahodaya tenant for '{$sahodayaOpt}'.");

            return self::FAILURE;
        }

        if ($commit) {
            $this->warn('--commit will write to this Sahodaya\'s live database. Confirm a fresh backup exists before continuing.');
            if (! $this->confirm('Proceed with --commit?', false)) {
                $this->info('Aborted — nothing was written.');

                return self::SUCCESS;
            }
        }

        $exitCode = self::FAILURE;

        try {
            $tenant->run(function () use ($eventOpt, $config, $commit, $migrator, &$exitCode) {
                $exitCode = $this->migrateEvent((int) $eventOpt, $config, $commit, $migrator);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function migrateEvent(int $eventId, array $config, bool $commit, FestRegionRoundMigrationService $migrator): int
    {
        $root = FestEvent::find($eventId);
        if (! $root) {
            $this->error("Event #{$eventId} not found.");

            return self::FAILURE;
        }

        try {
            $result = $migrator->migrate($root, $config, commit: $commit);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printPlan($result);

        if (! $commit) {
            $this->newLine();
            $this->info('Dry-run only — nothing was written. Re-run with --commit to apply.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Committed. Operational leaves:');
        $this->table(
            ['Leaf', 'Title', 'Phase', 'Region'],
            $result['leaves']->map(fn (FestEvent $l) => [$l->id, $l->title, $l->sourcePhase?->code, $l->region_id ?? '—'])->all()
        );
        $this->newLine();
        $this->comment('Next: re-run fest:audit-event-topology --event='.$root->id.' to confirm the new structure is consistent, and spot-check a few schools\' historical registrations and chest numbers in the admin UI.');

        return self::SUCCESS;
    }

    private function printPlan(array $result): void
    {
        $this->info('Batches:');
        $this->table(
            ['Code', 'Action', 'Name', 'Base fee'],
            $result['batches']->map(fn ($b) => [$b['code'], $b['action'], $b['model']->name, $b['model']->school_base_fee])->all()
        );

        $this->newLine();
        $this->info('Phases:');
        $this->table(
            ['Code', 'Action', 'Name', 'Regional', 'Region codes', 'Root items mapped'],
            $result['phases']->map(fn ($p) => [
                $p['code'], $p['action'], $p['model']->name,
                $p['model']->is_regional ? 'yes' : 'no',
                implode(', ', $p['region_codes']) ?: '—',
                $p['item_count'],
            ])->all()
        );

        if ($result['unmapped_items']->isNotEmpty()) {
            $this->newLine();
            $this->warn($result['unmapped_items']->count().' enabled item(s) on the root not covered by item_phase_map (blocking for --commit):');
            $this->table(
                ['Item', 'Code', 'Title'],
                $result['unmapped_items']->map(fn ($i) => [$i->id, $i->item_code, $i->title])->all()
            );
        }

        $this->newLine();
        $this->info('Legacy region round adoption:');
        foreach ($result['adoption'] as $plan) {
            $this->line("Event #{$plan['child']->id} \"{$plan['child']->title}\" (region: {$plan['region']->name}) → adopted as phase \"{$plan['targetPhase']->name}\"");
            $this->line("  Staying (no data moves): {$plan['stayingItems']->count()} item(s)");

            if ($plan['leavingItems']->isEmpty()) {
                $this->line('  Moving to another phase: none');

                continue;
            }

            $this->table(
                ['Item code', 'Title', 'Target phase', 'Registrations', 'Participants'],
                $plan['leavingItems']->map(function (array $leaving) {
                    $item = $leaving['item'];
                    $regCount = \App\Models\FestRegistration::where('item_id', $item->id)->count();
                    $partCount = \App\Models\FestParticipant::whereIn(
                        'registration_id',
                        \App\Models\FestRegistration::where('item_id', $item->id)->pluck('id')
                    )->count();

                    return [$item->item_code, $item->title, $leaving['targetPhaseCode'], $regCount, $partCount];
                })->all()
            );
        }
    }
}

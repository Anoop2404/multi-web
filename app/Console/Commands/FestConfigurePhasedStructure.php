<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestPhasedStructureConfigurator;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestPhaseTopologyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applies an MCS-style phase/batch/region/item-assignment config
 * (app/Support/data/mcs_kalotsav_phase_plan.php) to a root FestEvent and, on --commit,
 * enables phased_regional_billing and materializes the operational leaf events.
 *
 * Always run fest:audit-region-conversion-readiness against the same event first -- this
 * command assumes the event has no operational/financial data to preserve (Branch A). If
 * that audit reports registrations already exist, do not run this with --commit; a
 * relocation step is required first so existing registrations aren't orphaned by the
 * workflow_mode switch (see docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md Milestone 10).
 *
 * Dry-run (default) previews every batch/phase/region/item change and blocks --commit
 * while any enabled item is left unmapped, since FestRegistrationRouterService hard-rejects
 * registration for a phase-less item once phased mode is live.
 */
class FestConfigurePhasedStructure extends Command
{
    protected $signature = 'fest:configure-phased-structure
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Root fest_events id to configure (required)}
        {--config= : Path to a PHP config file returning the plan array (defaults to app/Support/data/mcs_kalotsav_phase_plan.php)}
        {--commit : Persist the structure and enable phased_regional_billing (defaults to dry-run)}';

    protected $description = 'Configure an event with the MCS four-phase / two-level structure using the existing phased_regional_billing engine';

    public function handle(FestPhasedStructureConfigurator $configurator): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        if (! $sahodayaOpt || ! $eventOpt) {
            $this->error('Both --sahodaya and --event are required.');

            return self::FAILURE;
        }

        $configPath = $this->option('config') ?: app_path('Support/data/mcs_kalotsav_phase_plan.php');
        if (! is_file($configPath)) {
            $this->error("Config file not found: {$configPath}");

            return self::FAILURE;
        }
        $config = require $configPath;
        if (! is_array($config)) {
            $this->error("Config file must return an array: {$configPath}");

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

        $exitCode = self::FAILURE;

        try {
            $tenant->run(function () use ($eventOpt, $config, $commit, $configurator, &$exitCode) {
                $exitCode = $this->configureEvent((int) $eventOpt, $config, $commit, $configurator);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function configureEvent(int $eventId, array $config, bool $commit, FestPhasedStructureConfigurator $configurator): int
    {
        $root = FestEvent::find($eventId);
        if (! $root) {
            $this->error("Event #{$eventId} not found.");

            return self::FAILURE;
        }

        // Preview is always computed read-only first, regardless of --commit, so the
        // report reflects exactly what a subsequent --commit would do.
        $preview = $configurator->configure($root, $config, commit: false);
        $this->printPlan($preview);

        if (! $commit) {
            $this->newLine();
            $this->info('Dry-run only -- nothing was written. Re-run with --commit to apply.');

            return self::SUCCESS;
        }

        if ($preview['unmapped_items']->isNotEmpty()) {
            $this->newLine();
            $this->error('Refusing to --commit: unmapped items above must be assigned to a phase first. Nothing was written.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($root, $config, $configurator) {
            $configurator->configure($root, $config, commit: true);

            $root->update([
                'workflow_mode' => FestPhasedWorkflowService::MODE,
                'phase_mode_enabled' => true,
                // 'partitioned', not 'standard' -- matches the field set
                // FestPhasedRegionalBillingWorkflowTest's fixture uses, and keeps
                // fest:audit-event-topology's standard_event_with_children check (which
                // only fires when conduct_mode is still 'standard') from false-positiving
                // on a root that now legitimately has phase/region leaf children.
                'conduct_mode' => 'partitioned',
            ]);

            $leaves = app(FestPhaseTopologyService::class)->sync($root->fresh());

            $this->newLine();
            $this->info("Committed. workflow_mode={$root->fresh()->workflow_mode}. Synchronized {$leaves->count()} operational leaf event(s):");
            $this->table(
                ['Leaf', 'Title', 'Phase', 'Region'],
                $leaves->map(fn (FestEvent $l) => [$l->id, $l->title, $l->sourcePhase?->code, $l->region_id ?? '—'])->all()
            );
        });

        $this->newLine();
        $this->comment('Next: re-run fest:audit-event-topology --event='.$root->id.' to confirm the new structure is consistent.');

        return self::SUCCESS;
    }

    private function printPlan(array $preview): void
    {
        $this->info('Batches:');
        $this->table(
            ['Code', 'Action', 'Name', 'Base fee'],
            $preview['batches']->map(fn ($b) => [$b['code'], $b['action'], $b['model']->name, $b['model']->school_base_fee])->all()
        );

        $this->newLine();
        $this->info('Phases:');
        $this->table(
            ['Code', 'Action', 'Name', 'Regional', 'Region codes', 'Items mapped'],
            $preview['phases']->map(fn ($p) => [
                $p['code'], $p['action'], $p['model']->name,
                $p['model']->is_regional ? 'yes' : 'no',
                implode(', ', $p['region_codes']) ?: '—',
                $p['item_count'],
            ])->all()
        );

        if ($preview['unmapped_items']->isNotEmpty()) {
            $this->newLine();
            $this->warn($preview['unmapped_items']->count().' enabled item(s) not covered by item_phase_map (blocking for --commit):');
            $this->table(
                ['Item', 'Code', 'Title'],
                $preview['unmapped_items']->map(fn ($i) => [$i->id, $i->item_code, $i->title])->all()
            );
        }
    }
}

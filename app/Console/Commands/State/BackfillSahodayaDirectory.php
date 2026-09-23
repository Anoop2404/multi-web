<?php

namespace App\Console\Commands\State;

use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Services\State\StateSahodayaDirectory;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase 1 of the State Kalotsav module: builds the canonical Sahodaya directory from the intakes
 * that already exist, and stamps their identity onto those intakes and their registrations.
 *
 * Every State row before this keyed on the raw source_tenant_id — a tenant uuid, or
 * "external:{uuid}" for a Sahodaya not on the platform. This walks the distinct keys, resolves each
 * to one directory row, and writes the canonical id and the name snapshot back.
 *
 * Safe to re-run: resolution is find-or-create per source key, and rows already carrying a
 * canonical id are left alone unless --force is given.
 */
class BackfillSahodayaDirectory extends Command
{
    protected $signature = 'state:backfill-sahodaya-directory
        {--dry-run : Print what would be written and exit}
        {--force   : Re-resolve rows that already carry a canonical id}';

    protected $description = 'Build the canonical State Sahodaya directory and stamp it onto existing intakes and registrations';

    public function handle(StateSahodayaDirectory $directory): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = StateQualifierIntake::query()
            ->when(! $force, fn ($q) => $q->whereNull('sahodaya_id'));

        $intakes = $query->get();

        if ($intakes->isEmpty()) {
            $this->info('Nothing to backfill — every intake already has a canonical Sahodaya.');

            return Command::SUCCESS;
        }

        $this->info(($dryRun ? 'Dry run — ' : '').$intakes->count().' intake(s) to resolve.');
        $this->newLine();

        $rows = [];
        $resolved = 0;
        $unresolved = [];

        // Grouped by source key so one directory row is created per Sahodaya, not per intake.
        foreach ($intakes->groupBy('source_tenant_id') as $sourceKey => $group) {
            $stateId = $group->first()->state_id;

            try {
                $sahodaya = $dryRun
                    ? $this->preview($directory, (string) $sourceKey, $stateId)
                    : $directory->resolve((string) $sourceKey, $stateId);
            } catch (Throwable $e) {
                $unresolved[(string) $sourceKey] = $e->getMessage();
                $rows[] = [(string) $sourceKey, '—', '—', $group->count(), 'UNRESOLVED'];

                continue;
            }

            $rows[] = [
                (string) $sourceKey,
                $sahodaya?->name ?? '—',
                $sahodaya?->origin ?? '—',
                $group->count(),
                $dryRun ? 'would link' : 'linked',
            ];

            if ($dryRun || ! $sahodaya) {
                continue;
            }

            $ids = $group->pluck('id');
            StateQualifierIntake::whereIn('id', $ids)
                ->update(['sahodaya_id' => $sahodaya->id, 'sahodaya_name' => $sahodaya->name]);

            // Registrations carried a copy of the raw key; move them onto the canonical id too.
            StateFestRegistration::whereIn('qualifier_entry_id', function ($q) use ($ids) {
                $q->select('id')->from('state_qualifier_entries')->whereIn('intake_id', $ids);
            })->update(['sahodaya_id' => $sahodaya->id, 'sahodaya_name' => $sahodaya->name]);

            $resolved++;
        }

        $this->table(['Source key', 'Sahodaya', 'Origin', 'Intakes', 'Status'], $rows);

        if ($unresolved) {
            $this->newLine();
            $this->warn('Could not resolve '.count($unresolved).' source key(s) — these keep their raw key:');
            foreach ($unresolved as $key => $message) {
                $this->line("  - {$key}: {$message}");
            }
            $this->line('A key matching neither a Sahodaya tenant nor an outside Sahodaya is usually a manual test intake.');
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Nothing was written. Re-run without --dry-run to apply.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("Linked {$resolved} Sahodaya identity/identities. Directory now holds ".StateSahodaya::count().' row(s).');

        return $unresolved ? Command::FAILURE : Command::SUCCESS;
    }

    /** Resolution without the create, so --dry-run genuinely writes nothing. */
    private function preview(StateSahodayaDirectory $directory, string $sourceKey, ?string $stateId): ?StateSahodaya
    {
        if (StateSahodayaDirectory::isExternalKey($sourceKey)) {
            $external = \App\Models\ExternalSahodaya::find(StateSahodayaDirectory::externalIdFromKey($sourceKey));
            if (! $external) {
                throw new \RuntimeException("No outside Sahodaya matches \"{$sourceKey}\".");
            }

            return StateSahodaya::query()->where('external_sahodaya_id', $external->id)->first()
                ?? new StateSahodaya(['name' => $external->name, 'origin' => StateSahodaya::ORIGIN_EXTERNAL]);
        }

        $tenant = \App\Models\Tenant::query()->where('type', 'sahodaya')->find($sourceKey);
        if (! $tenant) {
            throw new \RuntimeException("No Sahodaya tenant matches \"{$sourceKey}\".");
        }

        return StateSahodaya::query()->where('tenant_id', $tenant->id)->first()
            ?? new StateSahodaya(['name' => $tenant->name, 'origin' => StateSahodaya::ORIGIN_MANAGED]);
    }
}

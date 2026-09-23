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
        {--all     : Also seed a directory row for every Sahodaya tenant and outside Sahodaya, not only those that have submitted an intake}
        {--force   : Re-resolve rows that already carry a canonical id}
        {--state=  : State code (e.g. KL) or uuid to stamp on directory rows that have none}';

    protected $description = 'Build the canonical State Sahodaya directory and stamp it onto existing intakes and registrations';

    public function handle(StateSahodayaDirectory $directory): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        // Programs and tenants created before multi-state carry a null state_id, so directory rows
        // derived from them inherit one — and a state-scoped user then sees nothing at all. Stamping
        // is explicit rather than guessed from "there is only one state", which would do the wrong
        // thing the day there are two.
        if ($stateCode = $this->option('state')) {
            $stateId = \App\Models\PlatformState::query()
                ->when(\Illuminate\Support\Str::isUuid($stateCode),
                    fn ($q) => $q->where('id', $stateCode),
                    fn ($q) => $q->whereRaw('upper(code) = ?', [strtoupper(trim($stateCode))]))
                ->value('id');

            if (! $stateId) {
                $this->error("No state matches \"{$stateCode}\".");

                return Command::FAILURE;
            }

            $stamped = StateSahodaya::whereNull('state_id')->update(['state_id' => $stateId]);
            $this->line("Stamped state on {$stamped} directory row(s) that had none.");

            // The whole State module scopes on state, so a program or event left with a null one is
            // invisible to every state user — they fail closed and see a 403 on their own event.
            // Stamped together with the directory so the chain is consistent in one pass.
            $programs = \App\Models\FestStateProgram::whereNull('state_id')->update(['state_id' => $stateId]);
            $events = \App\Models\State\StateFestEvent::whereNull('state_id')->update(['state_id' => $stateId]);
            $intakes = \App\Models\State\StateQualifierIntake::whereNull('state_id')->update(['state_id' => $stateId]);
            $externals = \App\Models\ExternalSahodaya::whereNull('state_id')->update(['state_id' => $stateId]);
            $this->line("Stamped state on {$programs} program(s), {$events} event(s), {$intakes} intake(s) and {$externals} outside-Sahodaya row(s).");
        }


        // Phase 11 of the module plan. Without --all the directory only ever holds Sahodayas that
        // have already submitted something, so the State admin's Sahodaya filter lists three of
        // twenty-two and an operator concludes the rest are missing from the platform. Seeding the
        // whole directory up front is what makes the filter, the slot matrix and the catering roster
        // usable before the first intake arrives.
        if ($this->option('all')) {
            $seeded = $this->seedEveryKnownSahodaya($directory, $dryRun);

            $this->line(($dryRun ? 'Would create ' : 'Created ')."{$seeded['created']} directory row(s) "
                ."({$seeded['tenants']} Sahodaya tenant(s), {$seeded['externals']} outside Sahodaya/Sahodayas seen).");
            $this->newLine();
        }

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

    /**
     * Seed the directory from the platform's own records rather than from intakes.
     *
     * A promoted Sahodaya is deliberately resolved through its ExternalSahodaya row rather than its
     * tenant: linkPromotedTenant() folds the two into the single external-origin identity, so
     * seeding tenants first would create a second row for the same Sahodaya that later has to be
     * absorbed. Externals first, tenants second, and a tenant already linked is skipped.
     *
     * @return array{created: int, tenants: int, externals: int}
     */
    private function seedEveryKnownSahodaya(StateSahodayaDirectory $directory, bool $dryRun): array
    {
        $before = StateSahodaya::count();

        $externals = \App\Models\ExternalSahodaya::query()->get();
        foreach ($externals as $external) {
            if ($dryRun) {
                continue;
            }

            $sahodaya = $directory->forExternal($external, $external->state_id);

            // A promoted Sahodaya carries both identities on one row, so the tenant link is written
            // here rather than left for the tenant pass to duplicate.
            if ($external->tenant_id && ! $sahodaya->tenant_id) {
                $sahodaya->forceFill(['tenant_id' => $external->tenant_id])->save();
            }
        }

        $tenants = \App\Models\Tenant::query()
            ->where('type', 'sahodaya')
            ->whereNotNull('state_id')
            // Already represented by the external pass above.
            ->whereNotIn('id', $externals->pluck('tenant_id')->filter())
            ->get();

        foreach ($tenants as $tenant) {
            if ($dryRun) {
                continue;
            }

            $directory->forTenant($tenant, $tenant->state_id);
        }

        return [
            'created' => $dryRun ? $externals->count() + $tenants->count() : StateSahodaya::count() - $before,
            'tenants' => $tenants->count(),
            'externals' => $externals->count(),
        ];
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

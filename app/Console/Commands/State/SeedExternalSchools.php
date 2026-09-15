<?php

namespace App\Console\Commands\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Services\State\ExternalIntakeService;
use App\Support\ExcelImport;
use Illuminate\Console\Command;

/**
 * Bulk-creates ExternalSchool rows from the official school roster (columns: school_name,
 * sahodaya — the "Registered School List" export) — see chat conversation 2026-09-14.
 *
 * Matches each row's Sahodaya name against the ExternalSahodaya rows SeedExternalSahodayas
 * already created for this program. A row whose Sahodaya name doesn't match any of them is
 * skipped, not an error — that's expected for schools belonging to one of the Sahodayas
 * already on the platform (those never got an ExternalSahodaya row in the first place, so
 * their schools go through the normal school-tenant flow, not this one).
 */
class SeedExternalSchools extends Command
{
    protected $signature = 'state:seed-external-schools {csv : Path to the school roster CSV} {state-program-id : FestStateProgram UUID the Sahodayas were seeded under}';

    protected $description = 'Bulk-create ExternalSchool rows from the school roster CSV, matched to already-seeded outside Sahodayas';

    public function handle(ExternalIntakeService $service): int
    {
        $csvPath = $this->argument('csv');
        $program = FestStateProgram::find($this->argument('state-program-id'));

        if (! $program) {
            $this->error('No FestStateProgram found with that id.');

            return Command::FAILURE;
        }

        if (! is_readable($csvPath)) {
            $this->error("Cannot read file: {$csvPath}");

            return Command::FAILURE;
        }

        $sahodayasByName = ExternalSahodaya::where('state_program_id', $program->id)
            ->get()
            ->keyBy(fn (ExternalSahodaya $s) => strtolower(trim($s->name)));

        $rows = ExcelImport::associativeRows($csvPath)['rows'];
        $created = 0;
        $skippedExisting = 0;
        $skippedNoSahodaya = 0;

        foreach ($rows as $row) {
            $schoolName = trim($row['school_name'] ?? $row['school'] ?? '');
            $sahodayaName = trim($row['sahodaya'] ?? '');

            if ($schoolName === '' || $sahodayaName === '') {
                $skippedNoSahodaya++;

                continue;
            }

            $sahodaya = $sahodayasByName->get(strtolower($sahodayaName));
            if (! $sahodaya) {
                $skippedNoSahodaya++;

                continue;
            }

            $existing = $sahodaya->schools()
                ->whereRaw('lower(name) = ?', [strtolower($schoolName)])
                ->first();

            if ($existing) {
                $skippedExisting++;

                continue;
            }

            $service->addSchool($sahodaya, ['name' => $schoolName]);
            $created++;
        }

        $this->info("Done. Created {$created} schools, skipped {$skippedExisting} already present, skipped {$skippedNoSahodaya} with no matching outside Sahodaya (likely already on-platform).");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands\State;

use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Services\State\ExternalIntakeService;
use App\Support\ExcelImport;
use Illuminate\Console\Command;

/**
 * Bulk-creates ExternalSahodaya rows for the Sahodayas outside this platform's managed
 * tenants — see chat conversation 2026-09-14 and docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1.
 *
 * Expects a CSV with columns: sahodaya, district, email, mobile, on_our_platform (yes/no).
 * Rows flagged on_our_platform=yes are skipped — those Sahodayas already run on the platform
 * as real tenants and go through the normal outbox submission, not this intake. Re-running
 * against the same program is safe: matches existing rows by name instead of duplicating them.
 *
 * Also ensures the one shared "Appeal Sahodaya" exists for the program.
 */
class SeedExternalSahodayas extends Command
{
    protected $signature = 'state:seed-external-sahodayas {csv : Path to the Sahodaya master list CSV} {state-program-id : FestStateProgram UUID to attach these to}';

    protected $description = 'Bulk-create outside-Sahodaya intake rows from a CSV, skipping Sahodayas already on the platform';

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

        $rows = ExcelImport::associativeRows($csvPath)['rows'];
        $created = 0;
        $skippedOnPlatform = 0;
        $skippedExisting = 0;

        foreach ($rows as $row) {
            $name = trim($row['sahodaya'] ?? $row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if (strtolower(trim($row['on_our_platform'] ?? 'no')) === 'yes') {
                $skippedOnPlatform++;

                continue;
            }

            $existing = ExternalSahodaya::where('state_program_id', $program->id)
                ->whereRaw('lower(name) = ?', [strtolower($name)])
                ->first();

            if ($existing) {
                $skippedExisting++;

                continue;
            }

            $sahodaya = $service->createSahodaya($program, [
                'name'          => $name,
                'district'      => $row['district'] ?? null,
                'contact_email' => $row['email'] ?? null,
                'contact_phone' => $row['mobile'] ?? null,
                'source'        => 'seeded',
            ]);

            $this->line("Created {$sahodaya->name} — access code {$sahodaya->access_code}");
            $created++;
        }

        $appeal = $service->ensureAppealSahodaya($program);
        $this->line("Appeal Sahodaya — access code {$appeal->access_code}");

        $this->info("Done. Created {$created}, skipped {$skippedOnPlatform} already on platform, skipped {$skippedExisting} already seeded.");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use Illuminate\Console\Command;

/**
 * One-time import of the official Keralam State Sahodaya Kalotsavam item catalog into a
 * FestStateProgram, instead of hand-entering 140 items through StateFestProgramController::storeItem().
 *
 * The source data (app/Support/data/cksc_kalolsav_items.php) already matches the manual's
 * item list almost verbatim (same item codes 101-511, categories, genders, durations) — it
 * was built for the Sahodaya-side catalog preset, this command reuses it for the state catalog.
 *
 * Idempotent: safe to re-run (matches existing rows by item_code and updates them instead of duplicating).
 */
class ImportConfedKalotsavCatalog extends Command
{
    protected $signature = 'fest:import-state-catalog
        {program : FestStateProgram UUID to import items into}
        {--dry-run : Show what would be imported without writing anything}';

    protected $description = 'Import the official Keralam State Sahodaya Kalotsavam item catalog into a FestStateProgram';

    /**
     * cksc_kalolsav_items.php uses a broader category taxonomy than FestStateProgramItem's
     * admin-form validation allows (music,dance,drama,literary,sports,general) — normalize
     * the ones that don't map 1:1 rather than let them silently fail later manual edits.
     *
     * @var array<string, string>
     */
    private const CATEGORY_MAP = [
        'fine_arts'  => 'general',   // drawing/painting/cartoon/collage/poster
        'traditional' => 'dance',     // Thiruvathira, Oppana, Margam Kali, Kolkali, Duff Mutt
        'technology' => 'general',    // PowerPoint Presentation
    ];

    /**
     * Manual, General Rules #15: every item qualifies top 2 to State except English One Act
     * Play, capped at exactly 1 team. Everything else is left null so it falls through to the
     * event/program-level qualifier_policy default (see FestStateQualifierPayloadBuilder).
     *
     * @var array<string, int>
     */
    private const QUALIFY_COUNT_OVERRIDES = [
        '504' => 1, // One Act play (English Skit)
    ];

    public function handle(): int
    {
        $program = FestStateProgram::find($this->argument('program'));

        if (! $program) {
            $this->error('State program not found.');

            return self::FAILURE;
        }

        $path = base_path('app/Support/data/cksc_kalolsav_items.php');

        if (! is_file($path)) {
            $this->error("Catalog source file not found: {$path}");

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = require $path;
        $dryRun = (bool) $this->option('dry-run');

        $existingMax = (int) ($program->items()->max('display_order') ?? 0);
        $created = 0;
        $updated = 0;

        foreach ($rows as $index => $row) {
            $itemCode = (string) ($row['item_code'] ?? '');
            if ($itemCode === '') {
                $this->warn('Skipping row with no item_code: '.($row['title'] ?? '(untitled)'));

                continue;
            }

            $category = $row['category'] ?? null;
            $category = self::CATEGORY_MAP[$category] ?? $category;

            $attributes = [
                'title'              => $row['title'] ?? $itemCode,
                'category'           => $category,
                'stage_type'         => $row['stage_type'] ?? null,
                'participant_type'   => $row['participant_type'] ?? 'individual',
                'gender'             => $row['gender'] ?? 'mixed',
                'class_group'        => $row['class_group'] ?? null,
                'duration_minutes'   => $row['duration_minutes'] ?? null,
                'min_group_size'     => $row['min_group_size'] ?? null,
                'max_group_size'     => $row['max_group_size'] ?? null,
                'qualify_count'      => self::QUALIFY_COUNT_OVERRIDES[$itemCode] ?? null,
                'display_order'      => $existingMax + $index + 1,
            ];

            if ($dryRun) {
                $this->line("[dry-run] {$itemCode} — {$attributes['title']} ({$attributes['category']}, {$attributes['class_group']})");

                continue;
            }

            $item = FestStateProgramItem::where('state_program_id', $program->id)
                ->where('item_code', $itemCode)
                ->first();

            if ($item) {
                $item->update($attributes);
                $updated++;
            } else {
                FestStateProgramItem::create($attributes + [
                    'state_program_id' => $program->id,
                    'item_code'        => $itemCode,
                ]);
                $created++;
            }
        }

        if ($dryRun) {
            $this->info(count($rows).' item(s) would be imported (dry run — nothing written).');

            return self::SUCCESS;
        }

        $this->info("Imported catalog into \"{$program->title}\": {$created} created, {$updated} updated.");

        if ($program->status === 'published') {
            $this->warn('This program is already published — re-run the publish action (or FestItemSyncService::syncProgramToAllPropagations) to push these items to Sahodaya tenants.');
        }

        return self::SUCCESS;
    }
}

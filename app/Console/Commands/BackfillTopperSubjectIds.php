<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\Tenant;
use App\Services\BoardResults\TopperSubjectMarkService;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the "English core" / "English Core" split (#161): historical
 * topper_subject_marks rows were saved with subject_id = null whenever the
 * label didn't exactly (case-sensitively) match a row in the central
 * `subjects` table, so the Subject Merit Register and subject-wise leader
 * board grouped them by raw text and split one subject into two lists.
 *
 * This walks every tenant's topper_subject_marks table, resolves (or
 * creates) a canonical subject_id per label via
 * TopperSubjectMarkService::resolveOrCreateSubjectId() — the same
 * case-insensitive logic now used on every new save — and merges any rows
 * that turn out to be duplicates of the same student/subject once the
 * casing is normalized (keeping the higher-marks row).
 */
class BackfillTopperSubjectIds extends Command
{
    protected $signature = 'board-results:backfill-subject-ids
                            {--tenant= : Sahodaya tenant id (defaults to all)}
                            {--dry-run : Preview changes without writing them}';

    protected $description = 'Resolve/normalize subject_id on topper_subject_marks and merge casing-duplicate subject rows';

    public function handle(TopperSubjectMarkService $subjectMarks): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->orderBy('name')
            ->get();

        if ($sahodayas->isEmpty()) {
            $this->warn('No Sahodaya tenants found.');

            return self::SUCCESS;
        }

        $totals = ['resolved' => 0, 'merged' => 0, 'created_subjects' => 0];

        foreach ($sahodayas as $sahodaya) {
            $this->info("Backfilling {$sahodaya->name} ({$sahodaya->id})");

            TenancyDatabase::withTenantDatabase($sahodaya, function () use ($subjectMarks, $dryRun, &$totals) {
                if (! Schema::hasTable('topper_subject_marks')) {
                    $this->line('  no topper_subject_marks table here, skipping.');

                    return;
                }

                $subjectsBefore = Subject::query()->count();

                $rows = DB::table('topper_subject_marks')
                    ->orderBy('topper_id')
                    ->orderByDesc('marks')
                    ->get(['id', 'topper_id', 'subject_id', 'subject_label', 'marks']);

                // Group by topper so we can spot rows that will collide once
                // resolved to the same canonical subject_id.
                $byTopper = $rows->groupBy('topper_id');
                $resolved = 0;
                $merged = 0;

                foreach ($byTopper as $topperRows) {
                    $seenSubjectIds = []; // subject_id => kept row id

                    foreach ($topperRows as $row) {
                        $canonicalId = $subjectMarks->resolveOrCreateSubjectId($row->subject_label);

                        if ($canonicalId === null) {
                            continue;
                        }

                        if (isset($seenSubjectIds[$canonicalId])) {
                            // Duplicate of an already-kept row for this student+subject
                            // (e.g. "English core" and "English Core" for the same
                            // topper) — rows are pre-sorted by marks desc, so the one
                            // already kept has the higher (or equal) score. Drop this one.
                            $merged++;
                            $this->line("  merge topper #{$row->topper_id}: dropping duplicate '{$row->subject_label}' row #{$row->id} into #{$seenSubjectIds[$canonicalId]}");

                            if (! $dryRun) {
                                DB::table('topper_subject_marks')->where('id', $row->id)->delete();
                            }

                            continue;
                        }

                        $seenSubjectIds[$canonicalId] = $row->id;

                        if ((int) ($row->subject_id ?? 0) !== $canonicalId) {
                            $resolved++;

                            if (! $dryRun) {
                                DB::table('topper_subject_marks')->where('id', $row->id)->update(['subject_id' => $canonicalId]);
                            }
                        }
                    }
                }

                $createdSubjects = Subject::query()->count() - $subjectsBefore;

                $verb = $dryRun ? 'would resolve' : 'resolved';
                $this->line("  {$verb} {$resolved} row(s), merged {$merged} duplicate(s), created {$createdSubjects} new subject(s).");

                $totals['resolved'] += $resolved;
                $totals['merged'] += $merged;
                $totals['created_subjects'] += $createdSubjects;
            });
        }

        $verb = $dryRun ? 'Would resolve' : 'Resolved';
        $this->info("Done. {$verb} {$totals['resolved']} row(s), merged {$totals['merged']} duplicate(s), created {$totals['created_subjects']} subject(s) total.");

        if ($dryRun) {
            $this->comment('Dry run — no changes were written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}

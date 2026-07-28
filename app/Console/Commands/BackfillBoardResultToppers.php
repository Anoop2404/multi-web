<?php

namespace App\Console\Commands;

use App\Models\BoardResult;
use App\Models\BoardResultMarksConfig;
use App\Models\Tenant;
use App\Models\Topper;
use App\Support\BoardExamSubjects;
use App\Support\TenancyDatabase;
use App\Services\BoardResults\BoardResultMarksConfigService;
use Illuminate\Console\Command;

class BackfillBoardResultToppers extends Command
{
    protected $signature = 'board-results:backfill-legacy-toppers
                            {--tenant= : Sahodaya tenant id}
                            {--dry-run : Preview changes without writing them}';

    protected $description = 'Backfill legacy board result topper rows with current stream and marks rules';

    public function handle(BoardResultMarksConfigService $marksConfig): int
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

        $grandTotal = 0;
        foreach ($sahodayas as $sahodaya) {
            $grandTotal += $this->backfillTenant($sahodaya, $marksConfig, $dryRun);
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("Done. {$verb} {$grandTotal} topper row(s).");

        return self::SUCCESS;
    }

    private function backfillTenant(Tenant $sahodaya, BoardResultMarksConfigService $marksConfig, bool $dryRun): int
    {
        $updated = 0;
        $streamFixed = 0;
        $subjectWiseFixed = 0;
        $resultCount = 0;

        $this->info("Backfilling {$sahodaya->name} ({$sahodaya->id})");

        TenancyDatabase::withTenantDatabase($sahodaya, function () use (
            $sahodaya,
            $marksConfig,
            $dryRun,
            &$updated,
            &$streamFixed,
            &$subjectWiseFixed,
            &$resultCount,
        ) {
            BoardResult::query()
                ->with(['toppers.examStream', 'toppers.subjectMarks'])
                ->orderBy('id')
                ->chunkById(50, function ($results) use (
                    $sahodaya,
                    $marksConfig,
                    $dryRun,
                    &$updated,
                    &$streamFixed,
                    &$subjectWiseFixed,
                    &$resultCount,
                ) {
                    foreach ($results as $result) {
                        $resultCount++;

                        foreach ($result->toppers as $topper) {
                            $changes = $this->legacyChangesForTopper($sahodaya, $result, $topper, $marksConfig);

                            if ($changes === []) {
                                continue;
                            }

                            if (array_key_exists('stream', $changes) || array_key_exists('stream_id', $changes)) {
                                $streamFixed++;
                            }
                            if (array_key_exists('total_marks', $changes) && (int) $changes['total_marks'] === 100) {
                                $subjectWiseFixed++;
                            }

                            $updated++;

                            if (! $dryRun) {
                                $topper->update($changes);
                            }

                            $this->line('  #'.$topper->id.' '.implode(', ', array_map(
                                fn (string $field) => $field.'='.var_export($changes[$field], true),
                                array_keys($changes),
                            )));
                        }
                    }
                });
        });

        $verb = $dryRun ? 'would update' : 'updated';
        $this->info("  {$verb} {$updated} topper row(s) across {$resultCount} board result(s).");
        if ($streamFixed > 0) {
            $this->line("  stream fixes: {$streamFixed}");
        }
        if ($subjectWiseFixed > 0) {
            $this->line("  subject-wise total fixes: {$subjectWiseFixed}");
        }

        return $updated;
    }

    /** @return array<string, int|float|string|null> */
    private function legacyChangesForTopper(Tenant $sahodaya, BoardResult $result, Topper $topper, BoardResultMarksConfigService $marksConfig): array
    {
        $changes = [];
        $isClass12 = (int) $result->class === 12;
        $subjectMarks = collect(
            $topper->relationLoaded('subjectMarks')
                ? $topper->getRelation('subjectMarks')
                : $topper->subjectMarks()->get()
        );
        $hasSubjectMarks = $subjectMarks->isNotEmpty();

        if ($isClass12) {
            $streamKey = BoardExamSubjects::normalizeStream($topper->stream, $sahodaya->id);
            $resolvedStreamId = $streamKey ? BoardExamSubjects::resolveStreamId($streamKey, $sahodaya->id) : null;
            $currentStreamId = $topper->stream_id ? (int) $topper->stream_id : null;
            $streamId = $currentStreamId;

            if ($resolvedStreamId !== null && (! $streamId || $topper->examStream === null)) {
                $streamId = $resolvedStreamId;
            }

            if ($streamId !== null && (int) $topper->stream_id !== $streamId) {
                $changes['stream_id'] = $streamId;
            }

            if ($streamKey !== null) {
                $labels = BoardExamSubjects::class12StreamLabels($sahodaya->id);
                $normalizedLabel = $labels[$streamKey] ?? $topper->stream;
                if ($normalizedLabel !== null && $topper->stream !== $normalizedLabel) {
                    $changes['stream'] = $normalizedLabel;
                }
            } elseif ($topper->examStream?->label && $topper->stream !== $topper->examStream->label) {
                $changes['stream'] = $topper->examStream->label;
            }
        }

        if ($hasSubjectMarks && (int) $result->class === 12) {
            if ((int) ($topper->total_marks ?? 0) !== 100) {
                $changes['total_marks'] = 100;
            }

            if ($topper->marks_obtained !== null) {
                $changes['percentage'] = round((float) $topper->marks_obtained, 2);
            }

            return $changes;
        }

        if (! $isClass12) {
            return $changes;
        }

        $streamId = $changes['stream_id'] ?? $topper->stream_id;
        if (! $streamId && filled($topper->stream)) {
            $streamKey = BoardExamSubjects::normalizeStream($topper->stream, $sahodaya->id);
            $streamId = $streamKey ? BoardExamSubjects::resolveStreamId($streamKey, $sahodaya->id) : null;
        }

        if (! $streamId) {
            return $changes;
        }

        $configuredTotal = $marksConfig->resolve($sahodaya->id, 12, (int) $streamId);
        $legacyTotals = [0, BoardResultMarksConfig::DEFAULT_TOTAL_MARKS];
        if (in_array((int) ($topper->total_marks ?? 0), $legacyTotals, true) && (int) $topper->total_marks !== $configuredTotal) {
            $changes['total_marks'] = $configuredTotal;
        }

        if ($topper->marks_obtained !== null && $configuredTotal > 0) {
            $changes['percentage'] = round(((float) $topper->marks_obtained / $configuredTotal) * 100, 2);
        }

        return $changes;
    }
}

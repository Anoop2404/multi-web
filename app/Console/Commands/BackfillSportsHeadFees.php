<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BackfillSportsHeadFees extends Command
{
    protected $signature = 'fest:backfill-head-fees
                            {--sahodaya= : Sahodaya tenant id}
                            {--event= : Single fest event id (within the Sahodaya DB)}
                            {--dry-run : Print proposed values without saving}';

    protected $description = 'Backfill sports Event Head composite fee columns from existing item-level fee_amount rates';

    public function handle(): int
    {
        $sahodayaId = $this->option('sahodaya');
        $dryRun = (bool) $this->option('dry-run');
        $eventId = $this->option('event') ? (int) $this->option('event') : null;

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $sahodayas = $sahodayaId
            ? Tenant::query()->where('type', 'sahodaya')->whereKey($sahodayaId)->get()
            : Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");

            try {
                $sahodaya->run(function () use ($sahodaya, $eventId, $dryRun, &$totalUpdated, &$totalSkipped) {
                    if (! Schema::hasColumn('fest_item_heads', 'school_registration_fee')) {
                        $this->warn('  ✗ composite fee columns missing — run tenant migrations first.');

                        return;
                    }

                    [$updated, $skipped] = $this->backfillSahodaya($sahodaya, $eventId, $dryRun);
                    $totalUpdated += $updated;
                    $totalSkipped += $skipped;
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$sahodaya->name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would update' : 'Updated').": {$totalUpdated} head(s); skipped (already set): {$totalSkipped}.");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} [updated, skipped] */
    private function backfillSahodaya(Tenant $sahodaya, ?int $eventId, bool $dryRun): array
    {
        $events = FestEvent::query()
            ->where('event_type', 'sports')
            ->when($eventId, fn ($q) => $q->whereKey($eventId))
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            $this->line('  (no sports events)');

            return [0, 0];
        }

        $rows = [];
        $updated = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $heads = FestItemHead::query()
                ->where('event_id', $event->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($heads as $head) {
                if ($this->headAlreadyBackfilled($head)) {
                    $skipped++;

                    continue;
                }

                $items = FestEventItem::query()
                    ->where('event_id', $event->id)
                    ->where('head_id', $head->id)
                    ->get(['id', 'participant_type', 'fee_amount']);

                $studentFee = $this->modalFeeAmount(
                    $items->filter(fn (FestEventItem $item) => ! $item->isTeamItem())
                );
                $teamFee = $this->modalFeeAmount(
                    $items->filter(fn (FestEventItem $item) => $item->isTeamItem())
                );

                $payload = [
                    'school_registration_fee' => 0,
                    'student_registration_fee' => $studentFee,
                    'team_registration_fee' => $teamFee,
                    'included_items_per_student' => 0,
                    'included_teams' => 0,
                    'verification_policy' => 'all_students',
                    'approval_policy' => 'auto',
                ];

                $rows[] = [
                    'event' => $event->title,
                    'head' => $head->name,
                    'school' => number_format($payload['school_registration_fee'], 2),
                    'student' => number_format($payload['student_registration_fee'], 2),
                    'team' => number_format($payload['team_registration_fee'], 2),
                    'items' => (string) $items->count(),
                ];

                if (! $dryRun) {
                    $head->fill($payload);
                    $head->save();
                }

                $updated++;
            }
        }

        if ($rows !== []) {
            $this->table(
                ['Event', 'Head', 'School fee', 'Student fee', 'Team fee', 'Items'],
                $rows,
            );
        } else {
            $this->line('  (nothing to backfill — all heads already have composite fees set)');
        }

        $this->line("  {$sahodaya->name}: ".($dryRun ? 'would update' : 'updated')." {$updated}, skipped {$skipped}");

        return [$updated, $skipped];
    }

    /**
     * Idempotent guard: any of the three fee amount columns already set means a human
     * (or prior backfill) configured this head — leave it alone. Quota/policy defaults
     * from the migration do not count as "set".
     */
    private function headAlreadyBackfilled(FestItemHead $head): bool
    {
        return $head->school_registration_fee !== null
            || $head->student_registration_fee !== null
            || $head->team_registration_fee !== null;
    }

    /**
     * Most common non-null fee_amount among the given items. Ties break toward the
     * lower amount for determinism. Returns 0.0 when no fees are set.
     *
     * @param  Collection<int, FestEventItem>  $items
     */
    private function modalFeeAmount(Collection $items): float
    {
        $fees = $items
            ->pluck('fee_amount')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => number_format((float) $value, 2, '.', ''))
            ->values();

        if ($fees->isEmpty()) {
            return 0.0;
        }

        $counts = $fees->countBy();
        $maxCount = $counts->max();

        $mode = $counts
            ->filter(fn (int $count) => $count === $maxCount)
            ->keys()
            ->sort()
            ->first();

        return (float) $mode;
    }
}

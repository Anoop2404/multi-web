<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDuplicateSportsHeads extends Command
{
    protected $signature = 'fest:merge-duplicate-sports-heads
                            {--sahodaya= : Sahodaya tenant id}
                            {--force : Actually write changes. Without this flag, only previews.}';

    protected $description = 'Cleanup for the syncEventHeads()/promote() stale-lookup bug (fixed alongside this '
        .'command): merges a duplicate, un-promoted Event Head back onto its correctly-promoted sibling '
        .'(same catalog_key, same season) by reassigning any stray items/fees/participants onto the correct '
        .'head, migrating them onto the right discipline event, then deleting the now-empty duplicate. '
        .'Defaults to a dry run -- pass --force to write.';

    public function handle(\App\Services\Events\PromoteSportsHeadsToDisciplineEvents $promoter): int
    {
        $sahodayaId = $this->option('sahodaya');
        $force = (bool) $this->option('force');

        if (! $force) {
            $this->warn('Dry run — pass --force to actually write changes.');
        }

        $sahodayas = $sahodayaId
            ? Tenant::query()->where('type', 'sahodaya')->whereKey($sahodayaId)->get()
            : Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalMerged = 0;
        $totalSkipped = 0;

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");

            try {
                $sahodaya->run(function () use ($sahodaya, $force, $promoter, &$totalMerged, &$totalSkipped) {
                    $seasons = FestEvent::query()
                        ->where('event_type', 'sports')
                        ->whereNull('parent_event_id')
                        ->orderBy('id')
                        ->get();

                    foreach ($seasons as $season) {
                        [$merged, $skipped] = $this->processSeason($season, $force);
                        $totalMerged += $merged;
                        $totalSkipped += $skipped;

                        if ($merged > 0 && $force) {
                            $promoter->promote($season, false);
                            $this->line("  {$season->title}: ran promote() to sweep reassigned items onto their discipline events.");
                        }
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$sahodaya->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->newLine();
        $this->info(($force ? 'Merged' : 'Would merge')." {$totalMerged} duplicate head(s); skipped {$totalSkipped} (ambiguous, needs manual review).");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} [merged, skipped] */
    private function processSeason(FestEvent $season, bool $force): array
    {
        $disciplineEventIds = FestEvent::where('parent_event_id', $season->id)->pluck('id');

        $heads = FestItemHead::where(function ($q) use ($season, $disciplineEventIds) {
            $q->where('event_id', $season->id)->orWhereIn('event_id', $disciplineEventIds);
        })->whereNull('parent_id')->whereNotNull('catalog_key')->get();

        $merged = 0;
        $skipped = 0;

        foreach ($heads->groupBy('catalog_key') as $catalogKey => $group) {
            if ($group->count() < 2) {
                continue;
            }

            $keepers = $group->filter(fn (FestItemHead $h) => filled($h->discipline_event_id) && FestEvent::whereKey($h->discipline_event_id)->exists());
            $duplicates = $group->reject(fn (FestItemHead $h) => $keepers->contains('id', $h->id));

            if ($keepers->count() !== 1 || $duplicates->isEmpty()) {
                $this->warn("  {$season->title}: '{$catalogKey}' has ".$group->count()." heads but not exactly one clear keeper — skipping, needs manual review.");
                $skipped += $group->count();

                continue;
            }

            $keeper = $keepers->first();

            foreach ($duplicates as $duplicate) {
                $itemIds = FestEventItem::where('head_id', $duplicate->id)->pluck('id');
                $feeCount = FestSchoolEventFee::where('head_id', $duplicate->id)->count();
                $participantCount = FestParticipant::where('chest_head_id', $duplicate->id)->count();

                $this->line("  {$season->title}: '{$catalogKey}' — duplicate head #{$duplicate->id} ({$duplicate->name}): "
                    ."{$itemIds->count()} item(s), {$feeCount} fee row(s), {$participantCount} participant(s) -> keeper head #{$keeper->id} ({$keeper->name})");

                if (! $force) {
                    $merged++;

                    continue;
                }

                try {
                    DB::transaction(function () use ($duplicate, $keeper, $itemIds, $feeCount, $participantCount) {
                        if ($itemIds->isNotEmpty()) {
                            FestEventItem::whereIn('id', $itemIds)->update(['head_id' => $keeper->id]);
                        }

                        if ($feeCount > 0) {
                            FestSchoolEventFee::where('head_id', $duplicate->id)->update(['head_id' => $keeper->id]);
                        }

                        if ($participantCount > 0 && Schema::hasColumn('fest_participants', 'chest_head_id')) {
                            FestParticipant::where('chest_head_id', $duplicate->id)->update(['chest_head_id' => $keeper->id]);
                        }

                        // Only delete once nothing references it any more.
                        $remainingItems = FestEventItem::where('head_id', $duplicate->id)->count();
                        $remainingFees = FestSchoolEventFee::where('head_id', $duplicate->id)->count();
                        if ($remainingItems > 0 || $remainingFees > 0) {
                            throw new \RuntimeException("Duplicate head #{$duplicate->id} still has references after reassignment — not deleting.");
                        }

                        $duplicate->delete();
                    });

                    $merged++;
                } catch (\Throwable $e) {
                    // Isolated per duplicate — one bad pair shouldn't stop the rest of this
                    // season, or the rest of this tenant, from being processed.
                    $this->warn("    ✗ head #{$duplicate->id} ({$duplicate->name}): {$e->getMessage()}");
                    $skipped++;
                }
            }
        }

        return [$merged, $skipped];
    }
}

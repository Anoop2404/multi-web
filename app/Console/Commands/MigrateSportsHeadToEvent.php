<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Migrates Sports FestItemHead config onto discipline FestEvents (Head = Event).
 *
 * For each promoted (or unpromoted) head: copies fee/policy/schedule onto the sport
 * FestEvent, consolidates per-head fee rows, nulls item head_id, clears staff head scope.
 */
class MigrateSportsHeadToEvent extends Command
{
    protected $signature = 'fest:migrate-sports-head-to-event
                            {--sahodaya= : Sahodaya tenant id}
                            {--dry-run : Print proposed changes without saving}
                            {--delete-heads : After copy, delete runtime sports FestItemHead rows (not catalog templates)}';

    protected $description = 'Copy sports Event Head fields onto FestEvent and collapse per-head billing';

    public function handle(): int
    {
        $sahodayaId = $this->option('sahodaya');
        $dryRun = (bool) $this->option('dry-run');
        $deleteHeads = (bool) $this->option('delete-heads');

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

        $totals = ['events' => 0, 'fees_merged' => 0, 'items' => 0, 'heads_deleted' => 0, 'dupes_skipped' => 0];

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");

            try {
                $sahodaya->run(function () use ($dryRun, $deleteHeads, &$totals) {
                    if (! Schema::hasColumn('fest_events', 'school_registration_fee')) {
                        $this->warn('  ✗ fest_events unified columns missing — run tenant migrations first.');

                        return;
                    }

                    $result = $this->migrateSahodaya($dryRun, $deleteHeads);
                    foreach ($result as $k => $v) {
                        $totals[$k] = ($totals[$k] ?? 0) + $v;
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$sahodaya->name}: {$e->getMessage()}");
            } finally {
                // A failed initialize() can leave tenancy dangling on a broken tenant DB —
                // subsequent tenants then fail with the previous tenant's missing-database error.
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would process' : 'Processed';
        $this->info("{$verb}: {$totals['events']} sport event(s); fees merged: {$totals['fees_merged']}; items cleared: {$totals['items']}; heads deleted: {$totals['heads_deleted']}; duplicate heads skipped: {$totals['dupes_skipped']}.");

        if (! $deleteHeads) {
            $this->warn('Heads were kept (no --delete-heads). Old Event Heads still exist; school billing may stay dual-read until you re-run with --delete-heads after verifying sport events.');
        }

        return self::SUCCESS;
    }

    /** @return array{events: int, fees_merged: int, items: int, heads_deleted: int, dupes_skipped: int} */
    private function migrateSahodaya(bool $dryRun, bool $deleteHeads): array
    {
        $stats = ['events' => 0, 'fees_merged' => 0, 'items' => 0, 'heads_deleted' => 0, 'dupes_skipped' => 0];

        $seasons = FestEvent::query()
            ->where('event_type', 'sports')
            ->whereNull('parent_event_id')
            ->whereNull('conducting_school_id')
            ->orderBy('id')
            ->get();

        foreach ($seasons as $season) {
            $this->line("  Season: {$season->title} (#{$season->id})");

            if (! $dryRun && $season->partition_role !== 'sports_season') {
                $season->update(['partition_role' => 'sports_season']);
            }

            [$groups, $dupesSkipped] = $this->dedupeHeadsForSeason($season);
            $stats['dupes_skipped'] += $dupesSkipped;

            if ($dupesSkipped > 0) {
                $this->line("    (merging {$dupesSkipped} duplicate head row(s) into keepers — same catalog_key)");
            }

            $processedEventIds = [];

            foreach ($groups as $group) {
                /** @var Collection<int, FestItemHead> $group */
                $head = $this->pickKeeper($group);
                $siblingIds = $group->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $merged = $this->coalesceHeadFields($group);

                $sportEvent = $this->resolveOrCreateSportEvent($season, $head, $dryRun);
                if (! $sportEvent) {
                    continue;
                }

                // Already migrated this sport event from another keeper this pass.
                if ($sportEvent->exists && isset($processedEventIds[$sportEvent->id])) {
                    $this->line("    ↪ skip head #{$head->id} ({$head->name}) — already covered by event #{$sportEvent->id}");
                    $stats['dupes_skipped']++;

                    continue;
                }

                $this->table(
                    ['Field', 'Head (merged)', 'Event (after)'],
                    $this->diffRows($merged, $sportEvent),
                );

                if (! $dryRun) {
                    $this->copyHeadFieldsToEvent($merged, $sportEvent);
                    $this->migrateStrayRowsToSportEvent($season, $sportEvent, $siblingIds);
                    $stats['fees_merged'] += $this->consolidateFees($sportEvent, $siblingIds);
                    $stats['items'] += $this->clearItemHeadIds($sportEvent);
                    $this->clearStaffHeadScope($sportEvent);
                    $this->clearChestHeadIds($sportEvent);

                    if ($deleteHeads) {
                        FestItemHead::whereIn('id', $siblingIds)->delete();
                        $stats['heads_deleted'] += count($siblingIds);
                    } else {
                        FestItemHead::whereIn('id', $siblingIds)->update([
                            'event_id' => $sportEvent->id,
                            'discipline_event_id' => $sportEvent->id,
                        ]);
                    }

                    $processedEventIds[$sportEvent->id] = true;
                } else {
                    $stats['fees_merged'] += FestSchoolEventFee::query()
                        ->where(function ($q) use ($sportEvent, $season, $siblingIds) {
                            $q->where(function ($inner) use ($sportEvent, $siblingIds) {
                                $inner->where('event_id', $sportEvent->id)->whereIn('head_id', $siblingIds);
                            })->orWhere(function ($inner) use ($season, $siblingIds) {
                                $inner->where('event_id', $season->id)->whereIn('head_id', $siblingIds);
                            });
                        })
                        ->count();
                    $stats['items'] += FestEventItem::query()
                        ->where(function ($q) use ($sportEvent, $season, $siblingIds) {
                            $q->where(function ($inner) use ($sportEvent) {
                                $inner->where('event_id', $sportEvent->id)->whereNotNull('head_id');
                            })->orWhere(function ($inner) use ($season, $siblingIds) {
                                $inner->where('event_id', $season->id)->whereIn('head_id', $siblingIds);
                            });
                        })
                        ->count();
                    if ($deleteHeads) {
                        $stats['heads_deleted'] += count($siblingIds);
                    }
                    if ($sportEvent->exists) {
                        $processedEventIds[$sportEvent->id] = true;
                    }
                }

                $stats['events']++;
                $extra = count($siblingIds) > 1 ? ' (from '.count($siblingIds).' head rows)' : '';
                $this->line("    → {$sportEvent->title} (#{$sportEvent->id}){$extra}".($dryRun ? ' [dry-run]' : ''));
            }
        }

        // Orphan discipline events that already exist with source_head_id but empty fees
        FestEvent::query()
            ->where('event_type', 'sports')
            ->where('partition_role', 'sports_discipline')
            ->whereNotNull('source_head_id')
            ->each(function (FestEvent $event) use ($dryRun, &$stats) {
                if ($event->hasSportsFeesConfigured()) {
                    return;
                }
                $head = FestItemHead::find($event->source_head_id);
                if (! $head) {
                    return;
                }
                if (! $dryRun) {
                    $this->copyHeadFieldsToEvent($head, $event);
                    $stats['fees_merged'] += $this->consolidateFees($event, [(int) $head->id]);
                    $stats['items'] += $this->clearItemHeadIds($event);
                }
            });

        return $stats;
    }

    /**
     * Groups of heads per catalog_key (or singleton when catalog_key is null).
     * Within each group we keep one head for event resolve, but merge fields/rows from all siblings.
     *
     * @return array{0: Collection<int, Collection<int, FestItemHead>>, 1: int}
     */
    private function dedupeHeadsForSeason(FestEvent $season): array
    {
        $all = $this->headsForSeason($season);
        $skipped = 0;
        $groups = collect();

        $byKey = $all->groupBy(function (FestItemHead $h) {
            return filled($h->catalog_key) ? (string) $h->catalog_key : 'id:'.$h->id;
        });

        foreach ($byKey as $group) {
            /** @var Collection<int, FestItemHead> $group */
            if ($group->count() > 1) {
                $keeper = $this->pickKeeper($group);
                $skipped += $group->count() - 1;
                $dupeIds = $group->pluck('id')->reject(fn ($id) => (int) $id === (int) $keeper->id)->values()->all();
                $this->line('    catalog_key='.($keeper->catalog_key ?: 'null').": keep head #{$keeper->id}, merge siblings [".implode(', ', $dupeIds).']');
            }

            $groups->push($group->values());
        }

        return [$groups->values(), $skipped];
    }

    /** @param  Collection<int, FestItemHead>  $group */
    private function pickKeeper(Collection $group): FestItemHead
    {
        return $group->sortByDesc(function (FestItemHead $h) {
            $score = 0;
            if ($this->headHasFees($h)) {
                $score += 1000;
            }
            if (filled($h->discipline_event_id) && FestEvent::whereKey($h->discipline_event_id)->exists()) {
                $score += 100;
            }
            if ($h->event_id && FestEvent::whereKey($h->event_id)->whereNotNull('parent_event_id')->exists()) {
                $score += 10;
            }
            $score += (int) $h->id;

            return $score;
        })->first();
    }

    /**
     * Coalesce non-null fee/policy fields across duplicate heads into one virtual head for copy.
     *
     * @param  Collection<int, FestItemHead>  $group
     */
    private function coalesceHeadFields(Collection $group): FestItemHead
    {
        $keeper = $this->pickKeeper($group);
        $merged = $keeper->replicate();
        $merged->id = $keeper->id;
        $merged->exists = true;

        $fields = [
            'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
            'default_item_fee', 'extra_item_fee', 'included_items_per_student', 'included_teams',
            'verification_policy', 'approval_policy', 'max_participants', 'max_teams',
            'reg_start', 'reg_end', 'competition_start', 'competition_end',
            'schedule_mode', 'competition_time', 'notification_settings',
            'catalog_key', 'sport_discipline', 'is_team_heading', 'sort_order',
            'venue', 'event_start', 'event_end', 'status', 'slug', 'name',
        ];

        foreach ($fields as $field) {
            if ($merged->{$field} !== null && $merged->{$field} !== '') {
                continue;
            }
            foreach ($group as $sibling) {
                $val = $sibling->{$field};
                if ($val !== null && $val !== '') {
                    $merged->{$field} = $val;
                    break;
                }
            }
        }

        return $merged;
    }

    private function headHasFees(FestItemHead $head): bool
    {
        return $head->school_registration_fee !== null
            || $head->student_registration_fee !== null
            || $head->team_registration_fee !== null
            || $head->default_item_fee !== null
            || $head->extra_item_fee !== null;
    }

    /** @return Collection<int, FestItemHead> */
    private function headsForSeason(FestEvent $season): Collection
    {
        $childIds = FestEvent::where('parent_event_id', $season->id)->pluck('id');

        return FestItemHead::query()
            ->where(function ($q) use ($season, $childIds) {
                $q->where('event_id', $season->id)
                    ->orWhereIn('event_id', $childIds);
            })
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function resolveOrCreateSportEvent(FestEvent $season, FestItemHead $head, bool $dryRun): ?FestEvent
    {
        // 1. Explicit discipline link — only trust if catalog_key matches (or event has no key yet).
        if ($head->discipline_event_id) {
            $existing = FestEvent::find($head->discipline_event_id);
            if ($existing && $this->eventMatchesHead($existing, $head, $season)) {
                return $existing;
            }
        }

        // 2. Event that records this head as source.
        $bySource = FestEvent::query()
            ->where('parent_event_id', $season->id)
            ->where('source_head_id', $head->id)
            ->first();
        if ($bySource) {
            return $bySource;
        }

        // 3. Match by catalog_key first (strict), then partition_key / slug.
        if (filled($head->catalog_key)) {
            $byCatalog = FestEvent::query()
                ->where('parent_event_id', $season->id)
                ->where('catalog_key', $head->catalog_key)
                ->first();
            if ($byCatalog) {
                return $byCatalog;
            }
        }

        $slug = $head->slug ?: Str::slug($head->name);
        if ($slug !== '') {
            $byPartition = FestEvent::query()
                ->where('parent_event_id', $season->id)
                ->where('partition_key', $slug)
                ->first();
            if ($byPartition && $this->eventMatchesHead($byPartition, $head, $season)) {
                return $byPartition;
            }
        }

        // 4. Head already lives on a child sport event (only if that event matches).
        if ($head->event_id && (int) $head->event_id !== (int) $season->id) {
            $onEvent = FestEvent::find($head->event_id);
            if ($onEvent && $this->eventMatchesHead($onEvent, $head, $season)) {
                return $onEvent;
            }
        }

        if ($dryRun) {
            $fake = new FestEvent([
                'title' => $this->sportTitle($season, $head),
                'tenant_id' => $season->tenant_id,
                'catalog_key' => $head->catalog_key,
            ]);
            $fake->id = 0;
            $fake->exists = false;

            return $fake;
        }

        return DB::transaction(function () use ($season, $head) {
            $status = filled($head->status) ? $head->status : ($season->status ?: 'draft');
            if (! in_array($status, FestItemHead::STATUSES, true)) {
                $status = 'draft';
            }

            $sport = FestEvent::create([
                'tenant_id' => $season->tenant_id,
                'academic_year_id' => $season->academic_year_id,
                'title' => $this->sportTitle($season, $head),
                'event_type' => 'sports',
                'sport_discipline' => $head->sport_discipline,
                'catalog_key' => $head->catalog_key,
                'source_head_id' => $head->id,
                'is_team_heading' => (bool) $head->is_team_heading,
                'sort_order' => (int) ($head->sort_order ?? 0),
                'level_round' => $season->level_round ?: 'sahodaya',
                'parent_event_id' => $season->id,
                'partition_role' => 'sports_discipline',
                'partition_key' => $head->slug ?: Str::slug($head->name),
                'venue' => $head->venue ?: $season->venue,
                'event_start' => $head->event_start ?: $head->competition_start ?: $season->event_start,
                'event_end' => $head->event_end ?: $head->competition_end ?: $season->event_end,
                'registration_open' => $head->reg_start ?: $season->registration_open,
                'registration_close' => $head->reg_end ?: $season->registration_close,
                'event_reg_start' => $head->reg_start ?: $season->event_reg_start,
                'event_reg_end' => $head->reg_end ?: $season->event_reg_end,
                'sports_age_cutoff_date' => $season->sports_age_cutoff_date,
                'fee_settings' => array_merge(
                    is_array($season->fee_settings) ? $season->fee_settings : [],
                    ['fee_model' => 'sports_composite'],
                ),
                'numbering_settings' => $season->numbering_settings,
                'status' => $status,
                'description' => $season->description,
            ]);

            $this->copyHeadFieldsToEvent($head, $sport);
            $this->migrateStrayRowsToSportEvent($season, $sport, [(int) $head->id]);

            return $sport;
        });
    }

    /**
     * Prevent mis-linking (e.g. aquatics head → wrong "Sports 2026-27" event).
     * Event must be a child of this season (or the head's own event) and catalog/partition must agree when set.
     */
    private function eventMatchesHead(FestEvent $event, FestItemHead $head, FestEvent $season): bool
    {
        if ($event->event_type !== 'sports') {
            return false;
        }

        // Must be under this season (or be a child we already know about).
        if ($event->parent_event_id !== null && (int) $event->parent_event_id !== (int) $season->id) {
            return false;
        }
        // Never treat the season hub itself as a sport event target.
        if ((int) $event->id === (int) $season->id) {
            return false;
        }

        if (filled($head->catalog_key) && filled($event->catalog_key)
            && (string) $event->catalog_key !== (string) $head->catalog_key) {
            return false;
        }

        $slug = $head->slug ?: Str::slug((string) $head->name);
        if (filled($slug) && filled($event->partition_key)
            && filled($head->catalog_key) // only enforce when we have a catalog key
            && (string) $event->partition_key !== (string) $slug
            && (string) $event->catalog_key !== (string) $head->catalog_key) {
            return false;
        }

        return true;
    }

    private function copyHeadFieldsToEvent(FestItemHead $head, FestEvent $event): void
    {
        // Prefer head values when set; keep existing event values when head field is empty
        // so a re-run does not wipe fees already saved on the sport event.
        $prefer = static function ($headVal, $eventVal) {
            return ($headVal !== null && $headVal !== '') ? $headVal : $eventVal;
        };

        $event->fill([
            'catalog_key' => $prefer($head->catalog_key, $event->catalog_key),
            'is_team_heading' => $head->is_team_heading ?? $event->is_team_heading ?? true,
            'sort_order' => $head->sort_order ?? $event->sort_order ?? 0,
            'sport_discipline' => $prefer($head->sport_discipline, $event->sport_discipline),
            'source_head_id' => $head->id,
            'default_item_fee' => $prefer($head->default_item_fee, $event->default_item_fee),
            'extra_item_fee' => $prefer($head->extra_item_fee, $event->extra_item_fee),
            'school_registration_fee' => $prefer($head->school_registration_fee, $event->school_registration_fee),
            'student_registration_fee' => $prefer($head->student_registration_fee, $event->student_registration_fee),
            'team_registration_fee' => $prefer($head->team_registration_fee, $event->team_registration_fee),
            'included_items_per_student' => $head->included_items_per_student ?? $event->included_items_per_student ?? 0,
            'included_teams' => $head->included_teams ?? $event->included_teams ?? 0,
            'verification_policy' => $prefer($head->verification_policy, $event->verification_policy) ?? 'all_students',
            'approval_policy' => $prefer($head->approval_policy, $event->approval_policy) ?? 'auto',
            'max_participants' => $prefer($head->max_participants, $event->max_participants),
            'max_teams' => $prefer($head->max_teams, $event->max_teams),
            'reg_start' => $prefer($head->reg_start, $event->reg_start),
            'reg_end' => $prefer($head->reg_end, $event->reg_end),
            'competition_start' => $prefer($head->competition_start, $event->competition_start),
            'competition_end' => $prefer($head->competition_end, $event->competition_end),
            'schedule_mode' => $prefer($head->schedule_mode, $event->schedule_mode),
            'competition_time' => $prefer($head->competition_time, $event->competition_time),
            'notification_settings' => $prefer($head->notification_settings, $event->notification_settings),
            'partition_role' => 'sports_discipline',
            'partition_key' => $event->partition_key ?: ($head->slug ?: Str::slug($head->name)),
        ]);

        // Ensure fee_settings marks composite so billing does not look for heads.
        $feeSettings = is_array($event->fee_settings) ? $event->fee_settings : [];
        $feeSettings['fee_model'] = $feeSettings['fee_model'] ?? 'sports_composite';
        $event->fee_settings = $feeSettings;

        $event->save();
    }

    /** @param  list<int>  $headIds */
    private function migrateStrayRowsToSportEvent(FestEvent $season, FestEvent $sport, array $headIds): void
    {
        if ((int) $season->id === (int) $sport->id || $headIds === []) {
            return;
        }

        $itemIds = FestEventItem::where('event_id', $season->id)
            ->whereIn('head_id', $headIds)
            ->pluck('id');

        FestEventItem::whereIn('id', $itemIds)->update(['event_id' => $sport->id]);

        $registrationIds = FestRegistration::where('event_id', $season->id)
            ->whereIn('item_id', $itemIds)
            ->pluck('id');

        FestRegistration::whereIn('id', $registrationIds)->update(['event_id' => $sport->id]);

        FestParticipant::whereIn('registration_id', $registrationIds)
            ->update(['event_id' => $sport->id]);

        FestSchoolEventFee::where('event_id', $season->id)
            ->whereIn('head_id', $headIds)
            ->update(['event_id' => $sport->id]);

        FestEventStaff::where('event_id', $season->id)
            ->whereIn('head_id', $headIds)
            ->update(['event_id' => $sport->id, 'head_id' => null]);
    }

    /**
     * Merge head-scoped fee rows into a single event-level fee per school.
     *
     * @param  list<int>|null  $headIds  Limit to these heads; null = all head-scoped rows on the event
     */
    private function consolidateFees(FestEvent $event, ?array $headIds = null): int
    {
        $merged = 0;
        $query = FestSchoolEventFee::where('event_id', $event->id)->whereNotNull('head_id');
        if ($headIds !== null) {
            $query->whereIn('head_id', $headIds);
        }
        $headFees = $query->get();

        foreach ($headFees->groupBy('school_id') as $schoolId => $fees) {
            $target = FestSchoolEventFee::firstOrNew([
                'event_id' => $event->id,
                'school_id' => $schoolId,
                'head_id' => null,
            ]);

            $totalDue = (float) $fees->sum('total_due');
            $amountPaid = (float) $fees->sum('amount_paid');
            $status = $this->mergeFeeStatus($fees->pluck('status')->all());

            if (! $target->exists) {
                $first = $fees->first();
                $target->fill([
                    'school_registration_fee' => $first->school_registration_fee,
                    'student_registration_fee' => $first->student_registration_fee,
                    'participation_fee' => $first->participation_fee,
                    'participation_item_count' => $first->participation_item_count,
                    'extra_item_fee' => $first->extra_item_fee,
                    'fee_receipt_id' => $first->fee_receipt_id,
                ]);
            }

            $target->total_due = round(max((float) $target->total_due, $totalDue), 2);
            $target->amount_paid = round(max((float) $target->amount_paid, $amountPaid), 2);
            $target->status = $status;
            $target->head_id = null;
            $target->save();

            foreach ($fees as $fee) {
                if ((int) $fee->id === (int) $target->id) {
                    continue;
                }
                if ($fee->fee_receipt_id && ! $target->fee_receipt_id) {
                    $target->update(['fee_receipt_id' => $fee->fee_receipt_id]);
                }
                $fee->delete();
                $merged++;
            }

            if ($fees->count() === 1 && (int) $fees->first()->id === (int) $target->id) {
                $fees->first()->update(['head_id' => null]);
                $merged++;
            }
        }

        return $merged;
    }

    /** @param  list<string|null>  $statuses */
    private function mergeFeeStatus(array $statuses): string
    {
        $priority = ['approved', 'proof_uploaded', 'uploaded', 'submitted', 'rejected', 'pending'];
        foreach ($priority as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return 'pending';
    }

    private function clearItemHeadIds(FestEvent $event): int
    {
        return FestEventItem::where('event_id', $event->id)
            ->whereNotNull('head_id')
            ->update(['head_id' => null]);
    }

    private function clearStaffHeadScope(FestEvent $event): void
    {
        FestEventStaff::where('event_id', $event->id)
            ->whereNotNull('head_id')
            ->update(['head_id' => null]);
    }

    private function clearChestHeadIds(FestEvent $event): void
    {
        if (! Schema::hasColumn('fest_participants', 'chest_head_id')) {
            return;
        }

        FestParticipant::where('event_id', $event->id)
            ->whereNotNull('chest_head_id')
            ->update(['chest_head_id' => null]);
    }

    private function sportTitle(FestEvent $season, FestItemHead $head): string
    {
        $year = $season->academicYear?->label;
        $base = $head->name;

        return $year ? "{$base} {$year}" : $base;
    }

    /** @return list<array{0: string, 1: mixed, 2: mixed}> */
    private function diffRows(FestItemHead $head, FestEvent $event): array
    {
        $fields = [
            'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
            'default_item_fee', 'extra_item_fee', 'included_items_per_student', 'included_teams',
            'verification_policy', 'approval_policy', 'catalog_key',
        ];

        $rows = [];
        foreach ($fields as $field) {
            $headVal = $head->{$field} ?? '—';
            if ($event->exists) {
                $cur = $event->{$field};
                $eventVal = $cur !== null && $cur !== '' ? $cur : '(will copy)';
            } else {
                $eventVal = '(new event)';
            }
            $rows[] = [$field, $headVal, $eventVal];
        }

        return $rows;
    }
}

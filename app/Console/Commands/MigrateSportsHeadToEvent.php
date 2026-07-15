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

        $totals = ['events' => 0, 'fees_merged' => 0, 'items' => 0, 'heads_deleted' => 0];

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
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would process' : 'Processed';
        $this->info("{$verb}: {$totals['events']} sport event(s); fees merged: {$totals['fees_merged']}; items cleared: {$totals['items']}; heads deleted: {$totals['heads_deleted']}.");

        return self::SUCCESS;
    }

    /** @return array{events: int, fees_merged: int, items: int, heads_deleted: int} */
    private function migrateSahodaya(bool $dryRun, bool $deleteHeads): array
    {
        $stats = ['events' => 0, 'fees_merged' => 0, 'items' => 0, 'heads_deleted' => 0];

        $seasons = FestEvent::query()
            ->where('event_type', 'sports')
            ->whereNull('parent_event_id')
            ->whereNull('conducting_school_id')
            ->orderBy('id')
            ->get();

        foreach ($seasons as $season) {
            $this->line("  Season: {$season->title} (#{$season->id})");

            // Ensure season is marked as sports_season
            if (! $dryRun && $season->partition_role !== 'sports_season') {
                $season->update(['partition_role' => 'sports_season']);
            }

            $heads = $this->headsForSeason($season);
            foreach ($heads as $head) {
                $sportEvent = $this->resolveOrCreateSportEvent($season, $head, $dryRun);
                if (! $sportEvent) {
                    continue;
                }

                $this->table(
                    ['Field', 'Head', 'Event (after)'],
                    $this->diffRows($head, $sportEvent),
                );

                if (! $dryRun) {
                    $this->copyHeadFieldsToEvent($head, $sportEvent);
                    $this->migrateStrayRowsToSportEvent($season, $sportEvent, $head);
                    $stats['fees_merged'] += $this->consolidateFees($sportEvent, $head->id);
                    $stats['items'] += $this->clearItemHeadIds($sportEvent);
                    $this->clearStaffHeadScope($sportEvent);
                    $this->clearChestHeadIds($sportEvent);

                    if ($deleteHeads) {
                        $head->delete();
                        $stats['heads_deleted']++;
                    } else {
                        // Keep head for dual-read fallback but point at sport event
                        $head->update([
                            'event_id' => $sportEvent->id,
                            'discipline_event_id' => $sportEvent->id,
                        ]);
                    }
                } else {
                    $stats['fees_merged'] += FestSchoolEventFee::where('event_id', $sportEvent->id)
                        ->where('head_id', $head->id)
                        ->count();
                    $stats['items'] += FestEventItem::where('event_id', $sportEvent->id)
                        ->where('head_id', $head->id)
                        ->count();
                    if ($deleteHeads) {
                        $stats['heads_deleted']++;
                    }
                }

                $stats['events']++;
                $this->line("    → {$sportEvent->title} (#{$sportEvent->id})".($dryRun ? ' [dry-run]' : ''));
            }
        }

        // Orphan discipline events that already exist with source_head_id but head gone
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
                    $stats['fees_merged'] += $this->consolidateFees($event, $head->id);
                    $stats['items'] += $this->clearItemHeadIds($event);
                }
            });

        return $stats;
    }

    /** @return \Illuminate\Support\Collection<int, FestItemHead> */
    private function headsForSeason(FestEvent $season)
    {
        $childIds = FestEvent::where('parent_event_id', $season->id)->pluck('id');

        return FestItemHead::query()
            ->where(function ($q) use ($season, $childIds) {
                $q->where('event_id', $season->id)
                    ->orWhereIn('event_id', $childIds);
            })
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    private function resolveOrCreateSportEvent(FestEvent $season, FestItemHead $head, bool $dryRun): ?FestEvent
    {
        if ($head->discipline_event_id) {
            $existing = FestEvent::find($head->discipline_event_id);
            if ($existing) {
                return $existing;
            }
        }

        $bySource = FestEvent::query()
            ->where('parent_event_id', $season->id)
            ->where('source_head_id', $head->id)
            ->first();
        if ($bySource) {
            return $bySource;
        }

        $byKey = FestEvent::query()
            ->where('parent_event_id', $season->id)
            ->where(function ($q) use ($head) {
                $q->where('catalog_key', $head->catalog_key)
                    ->orWhere('partition_key', $head->slug ?: Str::slug($head->name));
            })
            ->first();
        if ($byKey) {
            return $byKey;
        }

        // Head lives on its own event already (promoted, event_id = discipline)
        if ($head->event_id && (int) $head->event_id !== (int) $season->id) {
            $onEvent = FestEvent::find($head->event_id);
            if ($onEvent && $onEvent->event_type === 'sports') {
                return $onEvent;
            }
        }

        if ($dryRun) {
            $fake = new FestEvent([
                'id' => 0,
                'title' => $this->sportTitle($season, $head),
                'tenant_id' => $season->tenant_id,
            ]);
            $fake->id = 0;

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
            $this->migrateStrayRowsToSportEvent($season, $sport, $head);

            return $sport;
        });
    }

    private function copyHeadFieldsToEvent(FestItemHead $head, FestEvent $event): void
    {
        $event->fill([
            'catalog_key' => $head->catalog_key ?? $event->catalog_key,
            'is_team_heading' => $head->is_team_heading ?? $event->is_team_heading ?? true,
            'sort_order' => $head->sort_order ?? $event->sort_order ?? 0,
            'sport_discipline' => $head->sport_discipline ?? $event->sport_discipline,
            'source_head_id' => $head->id,
            'default_item_fee' => $head->default_item_fee ?? $event->default_item_fee,
            'extra_item_fee' => $head->extra_item_fee ?? $event->extra_item_fee,
            'school_registration_fee' => $head->school_registration_fee ?? $event->school_registration_fee,
            'student_registration_fee' => $head->student_registration_fee ?? $event->student_registration_fee,
            'team_registration_fee' => $head->team_registration_fee ?? $event->team_registration_fee,
            'included_items_per_student' => $head->included_items_per_student ?? $event->included_items_per_student ?? 0,
            'included_teams' => $head->included_teams ?? $event->included_teams ?? 0,
            'verification_policy' => $head->verification_policy ?? $event->verification_policy ?? 'all_students',
            'approval_policy' => $head->approval_policy ?? $event->approval_policy ?? 'auto',
            'max_participants' => $head->max_participants ?? $event->max_participants,
            'max_teams' => $head->max_teams ?? $event->max_teams,
            'reg_start' => $head->reg_start ?? $event->reg_start,
            'reg_end' => $head->reg_end ?? $event->reg_end,
            'competition_start' => $head->competition_start ?? $event->competition_start,
            'competition_end' => $head->competition_end ?? $event->competition_end,
            'schedule_mode' => $head->schedule_mode ?? $event->schedule_mode,
            'competition_time' => $head->competition_time ?? $event->competition_time,
            'notification_settings' => $head->notification_settings ?? $event->notification_settings,
            'partition_role' => 'sports_discipline',
            'partition_key' => $event->partition_key ?: ($head->slug ?: Str::slug($head->name)),
        ]);
        $event->save();
    }

    private function migrateStrayRowsToSportEvent(FestEvent $season, FestEvent $sport, FestItemHead $head): void
    {
        if ((int) $season->id === (int) $sport->id) {
            return;
        }

        $itemIds = FestEventItem::where('event_id', $season->id)
            ->where('head_id', $head->id)
            ->pluck('id');

        FestEventItem::whereIn('id', $itemIds)->update(['event_id' => $sport->id]);

        $registrationIds = FestRegistration::where('event_id', $season->id)
            ->whereIn('item_id', $itemIds)
            ->pluck('id');

        FestRegistration::whereIn('id', $registrationIds)->update(['event_id' => $sport->id]);

        FestParticipant::whereIn('registration_id', $registrationIds)
            ->update(['event_id' => $sport->id]);

        FestSchoolEventFee::where('event_id', $season->id)
            ->where('head_id', $head->id)
            ->update(['event_id' => $sport->id]);
    }

    /** Merge head-scoped fee rows into a single event-level fee per school. */
    private function consolidateFees(FestEvent $event, int $headId): int
    {
        $merged = 0;
        $headFees = FestSchoolEventFee::where('event_id', $event->id)
            ->where('head_id', $headId)
            ->get();

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

            // Point receipts at consolidated fee, then delete head-scoped duplicates
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

            // Also clear head_id if this was the only row and we "merged" onto itself
            if ($fees->count() === 1 && $fees->first()->id === $target->id) {
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
            $rows[] = [
                $field,
                $head->{$field} ?? '—',
                $event->exists ? ($event->{$field} ?? '(will copy)') : '(new event)',
            ];
        }

        return $rows;
    }
}

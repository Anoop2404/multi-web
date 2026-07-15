<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Promotes each FestItemHead on a Sports season hub into its own discipline FestEvent.
 */
class PromoteSportsHeadsToDisciplineEvents
{
    /**
     * @return array{
     *     can_promote: bool,
     *     pending_count: int,
     *     linked_count: int,
     *     head_count: int,
     *     heads: list<array{id: int, name: string, status: string}>
     * }
     */
    public function status(FestEvent $season): array
    {
        abort_unless($season->event_type === 'sports', 422, 'Only sports events can be promoted.');
        abort_unless($season->parent_event_id === null, 422, 'Promote from the season hub event only.');

        $heads = $this->headsForSeason($season)
            ->get(['id', 'name', 'status', 'discipline_event_id']);

        $linkedOnSeason = $heads->filter(fn (FestItemHead $h) => filled($h->discipline_event_id))->count();

        $pending = $heads->filter(function (FestItemHead $h) {
            if (! filled($h->discipline_event_id)) {
                return true;
            }

            return ! FestEvent::whereKey($h->discipline_event_id)->exists();
        });

        return [
            'can_promote' => $pending->isNotEmpty(),
            'pending_count' => $pending->count(),
            'linked_count' => $linkedOnSeason,
            'head_count' => $heads->count(),
            'heads' => $pending->map(fn (FestItemHead $h) => [
                'id' => $h->id,
                'name' => $h->name,
                'status' => $h->status ?: 'draft',
            ])->values()->all(),
        ];
    }

    /**
     * Whether calling promote() would actually do anything: either a head is still
     * un-promoted, or an already-promoted head has stray items left behind on the season
     * (assigned a head_id after the fact — a school registered before the item had a
     * head, an item got added and only linked to its head later, etc). Cheap to call on
     * every page load; promote() itself is not free (it always runs a handful of
     * queries per head even when nothing moves), so callers should gate on this first.
     */
    public function hasPendingWork(FestEvent $season): bool
    {
        if ($this->status($season)['can_promote']) {
            return true;
        }

        return FestEventItem::where('event_id', $season->id)->whereNotNull('head_id')->exists();
    }

    /**
     * @return list<array{head_id: int, head_name: string, event_id: int, title: string, skipped?: bool, dry_run?: bool}>
     */
    public function promote(FestEvent $season, bool $dryRun = false): array
    {
        abort_unless($season->event_type === 'sports', 422, 'Only sports events can be promoted.');
        abort_unless($season->parent_event_id === null, 422, 'Promote from the season hub event only.');

        $heads = $this->headsForSeason($season)->get();

        $created = [];

        foreach ($heads as $head) {
            if ($head->discipline_event_id) {
                $existing = FestEvent::find($head->discipline_event_id);
                if ($existing) {
                    // Already promoted — but items/registrations can still land on the season
                    // afterwards (a school registers before an item gets assigned a head, an
                    // admin adds a new item and only assigns its head later, etc). Sweep any of
                    // those stray rows onto the existing discipline event instead of silently
                    // leaving them behind every time this runs.
                    if (! $dryRun) {
                        DB::transaction(fn () => $this->migrateHeadData($season, $existing, $head, false));
                    }

                    $created[] = [
                        'head_id' => $head->id,
                        'head_name' => $head->name,
                        'event_id' => $existing->id,
                        'title' => $existing->title,
                        'skipped' => true,
                    ];
                    continue;
                }
            }

            if ($dryRun) {
                $created[] = [
                    'head_id' => $head->id,
                    'head_name' => $head->name,
                    'event_id' => 0,
                    'title' => $this->disciplineTitle($season, $head),
                    'dry_run' => true,
                ];
                continue;
            }

            $event = DB::transaction(function () use ($season, $head) {
                $status = filled($head->status) ? $head->status : ($season->status ?: 'draft');
                if (! in_array($status, FestItemHead::STATUSES, true)) {
                    $status = 'draft';
                }

                $discipline = FestEvent::create([
                    'tenant_id' => $season->tenant_id,
                    'academic_year_id' => $season->academic_year_id,
                    'title' => $this->disciplineTitle($season, $head),
                    'event_type' => 'sports',
                    'sport_discipline' => $head->sport_discipline,
                    'source_head_id' => $head->id,
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

                $this->migrateHeadData($season, $discipline, $head, true, $status);

                return $discipline;
            });

            $created[] = [
                'head_id' => $head->id,
                'head_name' => $head->name,
                'event_id' => $event->id,
                'title' => $event->title,
            ];
        }

        if (! $dryRun && $created !== []) {
            $season->update([
                'partition_role' => 'sports_season',
                'fee_settings' => array_merge(
                    is_array($season->fee_settings) ? $season->fee_settings : [],
                    ['fee_model' => 'sports_composite'],
                ),
            ]);
        }

        return $created;
    }

    /**
     * Moves everything for one head — event items, their registrations, marks,
     * participants, and school fees — from the season onto its discipline event. Scoped
     * by head_id/item_id so it's safe to call repeatedly: rows already living on the
     * discipline event simply won't match the `where('event_id', $season->id)` filters
     * a second time.
     */
    private function migrateHeadData(FestEvent $season, FestEvent $discipline, FestItemHead $head, bool $isNewEvent, ?string $status = null): void
    {
        $itemIds = FestEventItem::where('event_id', $season->id)
            ->where('head_id', $head->id)
            ->pluck('id');

        FestEventItem::whereIn('id', $itemIds)->update(['event_id' => $discipline->id]);

        $registrationIds = FestRegistration::where('event_id', $season->id)
            ->whereIn('item_id', $itemIds)
            ->pluck('id');

        FestRegistration::whereIn('id', $registrationIds)->update(['event_id' => $discipline->id]);
        FestMark::where('event_id', $season->id)
            ->whereIn('item_id', $itemIds)
            ->update(['event_id' => $discipline->id]);

        // Participants carry their own event_id too (FestNumberingService's chest-
        // number and level-registration-number sequences query FestParticipant by
        // event_id directly) — without this, everyone already registered before a
        // promotion becomes invisible to those sequences under the new discipline
        // event, and the next person to register risks a duplicate number.
        FestParticipant::whereIn('registration_id', $registrationIds)
            ->update(['event_id' => $discipline->id]);

        FestSchoolEventFee::where('event_id', $season->id)
            ->where('head_id', $head->id)
            ->update([
                'event_id' => $discipline->id,
            ]);

        if ($isNewEvent) {
            // Move the head row onto the discipline event (keeps fee columns / policies).
            $head->update([
                'event_id' => $discipline->id,
                'discipline_event_id' => $discipline->id,
                'status' => $status,
                'venue' => $discipline->venue,
                'event_start' => $discipline->event_start,
                'event_end' => $discipline->event_end,
            ]);
        }
    }

    private function disciplineTitle(FestEvent $season, FestItemHead $head): string
    {
        $year = $season->academicYear?->label;
        $base = $head->name;

        return $year ? "{$base} {$year}" : $base;
    }

    /**
     * Every top-level Event Head that belongs to $season, wherever it currently lives.
     *
     * Once a head is promoted, its own event_id moves from the season to its new
     * discipline event (that's the whole point) — so a naive `where('event_id',
     * $season->id)` only ever finds heads that are STILL un-promoted. That silently
     * "loses" every already-promoted head from status()/promote()'s view: head_count and
     * linked_count would shrink to 0 the moment every head is promoted, and
     * FestItemHeadService::syncEventHeads() would recreate a brand-new duplicate head
     * back on the season the next time anyone visits its Competition page (its own
     * `where('event_id', $season->id)` lookup for that catalog_key also comes back
     * empty). Matching on either the season's own id or any of its already-promoted
     * discipline events' ids keeps every head visible regardless of where it lives.
     */
    private function headsForSeason(FestEvent $season)
    {
        $promotedDisciplineEventIds = FestEvent::where('parent_event_id', $season->id)->pluck('id');

        return FestItemHead::where(function ($q) use ($season, $promotedDisciplineEventIds) {
            $q->where('event_id', $season->id)
                ->orWhereIn('event_id', $promotedDisciplineEventIds);
        })->whereNull('parent_id')->orderBy('sort_order');
    }
}

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

        $heads = FestItemHead::where('event_id', $season->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
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
     * @return list<array{head_id: int, head_name: string, event_id: int, title: string, skipped?: bool, dry_run?: bool}>
     */
    public function promote(FestEvent $season, bool $dryRun = false): array
    {
        abort_unless($season->event_type === 'sports', 422, 'Only sports events can be promoted.');
        abort_unless($season->parent_event_id === null, 422, 'Promote from the season hub event only.');

        $heads = FestItemHead::where('event_id', $season->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $created = [];

        foreach ($heads as $head) {
            if ($head->discipline_event_id) {
                $existing = FestEvent::find($head->discipline_event_id);
                if ($existing) {
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

                // Move the head row onto the discipline event (keeps fee columns / policies).
                $head->update([
                    'event_id' => $discipline->id,
                    'discipline_event_id' => $discipline->id,
                    'status' => $status,
                    'venue' => $discipline->venue,
                    'event_start' => $discipline->event_start,
                    'event_end' => $discipline->event_end,
                ]);

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

    private function disciplineTitle(FestEvent $season, FestItemHead $head): string
    {
        $year = $season->academicYear?->label;
        $base = $head->name;

        return $year ? "{$base} {$year}" : $base;
    }
}

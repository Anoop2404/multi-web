<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRankPoint;
use App\Models\FestRankPointTemplate;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Collection;

class FestRankPointService
{
    /** Standard school athletics: rank → championship points. Final fallback when nothing's configured. */
    public const ATHLETICS_STANDARD = [
        1 => 8,
        2 => 7,
        3 => 6,
        4 => 5,
        5 => 4,
        6 => 3,
    ];

    /** @return Collection<int, FestRankPointTemplate> */
    public function templatesForEvent(FestEvent $event): Collection
    {
        return FestRankPointTemplate::where('event_id', $event->id)
            ->with('points')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function templateForType(FestEvent $event, string $participantType): ?FestRankPointTemplate
    {
        return FestRankPointTemplate::where('event_id', $event->id)
            ->whereJsonContains('participant_types', $participantType)
            ->first();
    }

    public function createTemplate(FestEvent $event, string $name, array $participantTypes = []): FestRankPointTemplate
    {
        $template = FestRankPointTemplate::create([
            'event_id' => $event->id,
            'name' => $name,
            'participant_types' => [],
            'sort_order' => FestRankPointTemplate::where('event_id', $event->id)->max('sort_order') + 1,
        ]);

        if ($participantTypes !== []) {
            $this->assignTypes($template, $participantTypes);
        }

        return $template;
    }

    public function renameTemplate(FestRankPointTemplate $template, string $name): void
    {
        $template->update(['name' => $name]);
    }

    /**
     * Assign these participant_type values to this template, clearing each one from
     * whatever other template in the same event currently holds it — a type belongs to
     * at most one template per event.
     *
     * @param  list<string>  $participantTypes
     */
    public function assignTypes(FestRankPointTemplate $template, array $participantTypes): void
    {
        $others = FestRankPointTemplate::where('event_id', $template->event_id)
            ->where('id', '!=', $template->id)
            ->get();

        foreach ($others as $other) {
            $remaining = array_values(array_diff($other->participant_types ?? [], $participantTypes));
            if ($remaining !== ($other->participant_types ?? [])) {
                $other->update(['participant_types' => $remaining]);
            }
        }

        $template->update(['participant_types' => array_values(array_unique($participantTypes))]);
    }

    public function deleteTemplate(FestRankPointTemplate $template): void
    {
        $template->delete();
    }

    /** @param  list<array{rank: int|string, points: int|string}>  $rows */
    public function replaceRows(FestRankPointTemplate $template, array $rows): int
    {
        FestRankPoint::where('template_id', $template->id)->delete();

        $saved = 0;

        foreach ($rows as $row) {
            $rank = (int) ($row['rank'] ?? 0);
            if ($rank < 1) {
                continue;
            }

            $points = $row['points'] ?? null;
            if ($points === null || $points === '') {
                continue;
            }

            FestRankPoint::create([
                'event_id' => $template->event_id,
                'template_id' => $template->id,
                'rank' => $rank,
                'points' => max(0, (int) $points),
            ]);
            $saved++;
        }

        return $saved;
    }

    public function seedAthleticsStandard(FestRankPointTemplate $template): int
    {
        $rows = collect(self::ATHLETICS_STANDARD)
            ->map(fn (int $points, int $rank) => ['rank' => $rank, 'points' => $points])
            ->values()
            ->all();

        return $this->replaceRows($template, $rows);
    }

    public function pointsForRank(FestEvent $event, int $rank, string $participantType): int
    {
        if ($rank < 1) {
            return 0;
        }

        $template = $this->templateForType($event, $participantType);
        if ($template) {
            $configured = FestRankPoint::where('template_id', $template->id)->where('rank', $rank)->value('points');
            if ($configured !== null) {
                return (int) $configured;
            }
        }

        // Unassigned multi-person types (or a template with no row for this rank) fall
        // back to whatever the Individual-governing template gives for the same rank —
        // matches the pre-template-era "team/relay leave empty to fallback to individual"
        // behavior instead of silently awarding 0.
        if (FestTeamSquadRules::isMultiPerson($participantType)) {
            $individual = $this->templateForType($event, 'individual');
            $configured = $individual
                ? FestRankPoint::where('template_id', $individual->id)->where('rank', $rank)->value('points')
                : null;
            if ($configured !== null) {
                return (int) $configured;
            }
        }

        if ($event->event_type === 'sports') {
            return (int) (self::ATHLETICS_STANDARD[$rank] ?? 0);
        }

        return 0;
    }

    /**
     * Resolved rank→points rows for one participant_type, same fallback chain as
     * pointsForRank() but returning every rank at once — for handing to the frontend
     * (mark entry's live auto-fill) instead of resolving one rank per request.
     *
     * @return list<array{rank: int, points: int}>
     */
    public function rowsForType(FestEvent $event, string $participantType): array
    {
        $template = $this->templateForType($event, $participantType);
        $rows = $template ? $this->pointsMapForTemplate($template) : [];

        if ($rows === [] && FestTeamSquadRules::isMultiPerson($participantType)) {
            $individual = $this->templateForType($event, 'individual');
            $rows = $individual ? $this->pointsMapForTemplate($individual) : [];
        }

        if ($rows === [] && $event->event_type === 'sports') {
            $rows = self::ATHLETICS_STANDARD;
        }

        return collect($rows)
            ->map(fn (int $points, int $rank) => ['rank' => $rank, 'points' => $points])
            ->values()
            ->all();
    }

    /**
     * rowsForType() for every real participant_type at once — what mark-entry pages hand
     * to the frontend so it can resolve an item's auto-filled score client-side without a
     * round trip, instead of duplicating the fallback chain in JS.
     *
     * @return array<string, list<array{rank: int, points: int}>>
     */
    public function rowsForAllTypes(FestEvent $event): array
    {
        if ($event->event_type !== 'sports') {
            return [];
        }

        return collect(FestTeamSquadRules::ALL_TYPES)
            ->mapWithKeys(fn (string $type) => [$type => $this->rowsForType($event, $type)])
            ->all();
    }

    /** @return array<int, int> rank => points */
    private function pointsMapForTemplate(FestRankPointTemplate $template): array
    {
        return FestRankPoint::where('template_id', $template->id)
            ->pluck('points', 'rank')
            ->map(fn ($points) => (int) $points)
            ->all();
    }
}

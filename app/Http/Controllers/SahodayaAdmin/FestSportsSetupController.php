<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRankPoint;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestSportsEventSyncService;
use App\Support\FestPageActivity;
use App\Support\FestSportsAgeGroup;

class FestSportsSetupController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 404);

        $base = "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}";
        $tenantBase = "/sahodaya-admin/{$this->sahodaya->id}";

        // Season hub (tagged, or top-level with children): sync + season UI.
        // Standalone sport events and sport children: operate on self.
        $isSeason = $event->isSportsSeasonEvent();
        if ($isSeason) {
            app(FestSportsEventSyncService::class)->syncSeason($event);
        }

        $sportEvents = $isSeason
            ? FestEvent::where('parent_event_id', $event->id)
                ->ofType('sports')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
            : collect([$event]);

        $sportEventIds = $sportEvents->pluck('id');
        $headCount = $sportEvents->count();
        $itemCount = FestEventItem::whereIn('event_id', $sportEventIds)->where('is_enabled', true)->count();
        $itemsWithHead = $itemCount; // items live on sport events — all "linked"
        $headsWithDates = $sportEvents->filter(fn (FestEvent $e) => $e->reg_start || $e->competition_start || $e->registration_open)->count();
        $headsWithCompositeFees = $sportEvents->filter(fn (FestEvent $e) => $e->hasSportsFeesConfigured())->count();
        $headsWithFees = $headsWithCompositeFees;
        $itemsWithFees = FestEventItem::whereIn('event_id', $sportEventIds)->where('is_enabled', true)->whereNotNull('fee_amount')->count();
        $rankPointCount = FestRankPoint::whereIn('event_id', $sportEventIds)->count()
            + ($isSeason ? FestRankPoint::where('event_id', $event->id)->count() : 0);

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $storedFees = is_array($event->fee_settings) ? $event->fee_settings : [];
        $feeConfigured = $event->hasSportsFeesConfigured()
            || collect(['school_fee_cap', 'school_registration_fee', 'student_registration_fee', 'team_registration_fee', 'default_item_fee', 'extra_item_fee'])
                ->contains(fn (string $key) => filled($storedFees[$key] ?? null) || filled($schedule[$key] ?? null));

        $headsFullyConfigured = $headCount > 0 && $headsWithCompositeFees === $headCount;

        $registrationsQuery = \App\Models\FestRegistration::whereIn('event_id', $sportEvents->pluck('id'))
            ->whereIn('status', \App\Models\FestRegistration::ACTIVE_STATUSES)
            ->with('participants')
            ->get()
            ->groupBy('event_id');

        $headItemGroups = $sportEvents->map(function (FestEvent $sport) use ($registrationsQuery) {
            $regs = $registrationsQuery->get($sport->id) ?? collect();
            $schoolsCount = $regs->pluck('school_id')->unique()->count();
            $athletesCount = $regs->flatMap(fn($r) => $r->participants ?? [])->filter(fn($p) => $p->participant_role !== 'standby')->count();

            return [
                'head_id' => $sport->id,
                'head_name' => $sport->title,
                'href' => "/sahodaya-admin/{$this->sahodaya->id}/events/{$sport->id}",
                'item_count' => FestEventItem::where('event_id', $sport->id)->where('is_enabled', true)->count(),
                'fees_configured' => $sport->hasSportsFeesConfigured(),
                'registration_open' => $sport->registration_open,
                'registration_close' => $sport->registration_close,
                'event_start' => $sport->event_start,
                'event_end' => $sport->event_end,
                'schools_count' => $schoolsCount,
                'athletes_count' => $athletesCount,
            ];
        })->values()->all();

        $checklist = app(\App\Services\Events\FestSportsChecklist::class)->forSetupHub($event, [
            'base' => $base,
            'headCount' => $headCount,
            'itemCount' => $itemCount,
            'itemsWithHead' => $itemsWithHead,
            'headsWithDates' => $headsWithDates,
            'headsWithFees' => $headsWithFees,
            'headsFullyConfigured' => $headsFullyConfigured,
            'itemsWithFees' => $itemsWithFees,
            'rankPointCount' => $rankPointCount,
            'feeConfigured' => $feeConfigured,
        ]);

        $tenantMasters = [
            [
                'label' => 'Sports items master',
                'hint'  => 'Sahodaya-wide catalog — enable items and default fees before loading into events.',
                'href'  => "{$tenantBase}/sports/catalog?event_id={$event->id}",
            ],
            [
                'label' => 'Sport catalog',
                'hint'  => 'Default sports synced into each season (Athletics, Chess…).',
                'href'  => "{$tenantBase}/sports/catalog/heads?event_id={$event->id}",
            ],
            [
                'label' => 'Age categories master',
                'hint'  => 'U14, U17, Open — cutoff rules and age-group fees.',
                'href'  => "{$tenantBase}/sports/age-groups",
            ],
            [
                'label' => 'Sport discipline taxonomy',
                'hint'  => 'Athletics, Aquatics, Racket sports — used when creating sport events and items.',
                'href'  => "{$tenantBase}/taxonomy-masters?dimension=sport_discipline",
            ],
        ];

        $required = collect($checklist)->reject(fn (array $step) => ! empty($step['optional']));
        $doneCount = $required->where('done', true)->count();

        return $this->inertia('Sahodaya/Events/SportsSetup', $this->withEventActivity($event, FestPageActivity::SETTINGS, [
            'event'          => $event->only('id', 'title', 'status', 'event_type', 'registration_open', 'registration_close', 'results_published', 'parent_event_id', 'partition_role'),
            'checklist'      => $checklist,
            'checklistProgress' => [
                'done'  => $doneCount,
                'total' => $required->count(),
            ],
            'tenantMasters'  => $tenantMasters,
            'headItemGroups' => $headItemGroups,
            'stats'          => [
                'heads'  => $headCount,
                'items'  => $itemCount,
                'linked' => $itemsWithHead,
            ],
            'ageRuleSummary' => FestSportsAgeGroup::ageRuleSummary($event),
            'competitionUrl' => $isSeason ? "{$tenantBase}/sports" : "{$base}/items",
            'sportsHubUrl'   => "{$tenantBase}/sports",
            'isSeason'       => $isSeason,
            // Any top-level sports event may host child sports (adding the first
            // one turns it into a season container).
            'canAddSport'    => $event->parent_event_id === null,
            'addSportUrl'    => $event->parent_event_id === null ? "{$base}/setup/sports" : null,
        ]));
    }

    /**
     * Explicit "Add sport" — the only way new sport events are created (catalog
     * sports are no longer auto-seeded on page loads, and deleted sports stay
     * deleted). Names matching the catalog reuse its metadata and items.
     */
    public function storeSport(\Illuminate\Http\Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 404);
        abort_unless($event->parent_event_id === null, 422, 'Sports are added on the season, not under another sport.');

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'sport_discipline' => 'nullable|string|max:60',
            'is_team_heading' => 'nullable|boolean',
        ]);

        $sport = app(FestSportsEventSyncService::class)->addSport($event, $data);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$sport->id}")
            ->with('success', "Sport event \"{$sport->title}\" ready — configure items, fees, and open registration.");
    }
}

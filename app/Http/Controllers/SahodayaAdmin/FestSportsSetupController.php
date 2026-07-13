<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestRankPoint;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestSchoolEventFeeService;
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

        $headCount = FestItemHead::where('event_id', $event->id)->count();
        $itemCount = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->count();
        $itemsWithHead = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->whereNotNull('head_id')->count();
        $headsWithDates = FestItemHead::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNotNull('reg_start')->orWhereNotNull('competition_start');
            })
            ->count();
        $headsWithCompositeFees = FestItemHead::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNotNull('school_registration_fee')
                    ->orWhereNotNull('student_registration_fee')
                    ->orWhereNotNull('team_registration_fee')
                    ->orWhereNotNull('default_item_fee')
                    ->orWhereNotNull('extra_item_fee');
            })
            ->count();
        $headsWithFees = $headsWithCompositeFees;
        $itemsWithFees = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->whereNotNull('fee_amount')->count();
        $rankPointCount = FestRankPoint::where('event_id', $event->id)->count();

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $storedFees = is_array($event->fee_settings) ? $event->fee_settings : [];
        // Sports always has sports_composite — only count real event-wide overrides.
        $feeConfigured = collect(['school_fee_cap', 'school_registration_fee', 'student_registration_fee', 'team_registration_fee', 'default_item_fee', 'extra_item_fee'])
            ->contains(fn (string $key) => filled($storedFees[$key] ?? null) || filled($schedule[$key] ?? null));

        $headsFullyConfigured = $headCount > 0 && $headsWithCompositeFees === $headCount;

        $nav = app(FestHeadItemNavigationService::class)->navigationForEvent($event);

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
                'label' => 'Event Heads catalog',
                'hint'  => 'Default head groups synced into each sports event (Athletics, Chess…).',
                'href'  => "{$tenantBase}/sports/catalog/heads?event_id={$event->id}",
            ],
            [
                'label' => 'Age categories master',
                'hint'  => 'U14, U17, Open — cutoff rules and age-group fees.',
                'href'  => "{$tenantBase}/sports/age-groups",
            ],
            [
                'label' => 'Sport discipline taxonomy',
                'hint'  => 'Athletics, Aquatics, Racket sports — used when creating heads and items.',
                'href'  => "{$tenantBase}/taxonomy-masters?dimension=sport_discipline",
            ],
        ];

        // Optional steps (item / event-wide fee overrides) must not inflate “complete”.
        $required = collect($checklist)->reject(fn (array $step) => ! empty($step['optional']));
        $doneCount = $required->where('done', true)->count();

        $promoteStatus = $event->parent_event_id === null
            ? app(\App\Services\Events\PromoteSportsHeadsToDisciplineEvents::class)->status($event)
            : null;

        return $this->inertia('Sahodaya/Events/SportsSetup', $this->withEventActivity($event, FestPageActivity::SETTINGS, [
            'event'          => $event->only('id', 'title', 'status', 'event_type', 'registration_open', 'registration_close', 'results_published', 'parent_event_id', 'partition_role'),
            'checklist'      => $checklist,
            'checklistProgress' => [
                'done'  => $doneCount,
                'total' => $required->count(),
            ],
            'tenantMasters'  => $tenantMasters,
            'headItemGroups' => $nav['headItemGroups'] ?? [],
            'stats'          => [
                'heads'  => $headCount,
                'items'  => $itemCount,
                'linked' => $itemsWithHead,
            ],
            'ageRuleSummary' => FestSportsAgeGroup::ageRuleSummary($event),
            'competitionUrl' => "{$base}/competition",
            'sportsHubUrl'   => "{$tenantBase}/sports",
            'promoteStatus'  => $promoteStatus,
        ]));
    }
}
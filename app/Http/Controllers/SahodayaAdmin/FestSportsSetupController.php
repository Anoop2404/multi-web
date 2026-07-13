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
        $headsWithFees = FestItemHead::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNotNull('default_item_fee')->orWhereNotNull('extra_item_fee');
            })
            ->count();
        $itemsWithFees = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->whereNotNull('fee_amount')->count();
        $rankPointCount = FestRankPoint::where('event_id', $event->id)->count();

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $feeModel = $schedule['fee_model'] ?? $event->fee_settings['fee_model'] ?? null;
        $feeConfigured = $feeModel && $feeModel !== 'none';

        $nav = app(FestHeadItemNavigationService::class)->navigationForEvent($event);

        $checklist = [
            [
                'key'     => 'event',
                'label'   => 'Event details & dates',
                'hint'    => 'Title, status, fest dates, registration open/close.',
                'href'    => "{$base}?overview=1",
                'done'    => filled($event->title) && filled($event->status),
            ],
            [
                'key'     => 'heads',
                'label'   => 'Item heads & head scheduling',
                'hint'    => 'Create each head (Athletics, Chess…) — its fees, quota, and approval policy are set right there, like standing up its own event.',
                'href'    => "{$base}/competition",
                'done'    => $headCount > 0,
            ],
            [
                'key'     => 'fees',
                'label'   => 'Event fee settings (optional overrides)',
                'hint'    => 'Billing model defaults to Sports composite automatically. Only visit this if you need event-wide fallback amounts or a fee cap — per-head fees set at head creation take priority.',
                'href'    => "{$base}/settings/fees",
                'done'    => $feeConfigured,
            ],
            [
                'key'     => 'items',
                'label'   => 'Items under heads',
                'hint'    => 'List, create, import, enable/disable, and move items under their item heads.',
                'href'    => "{$base}/items",
                'done'    => $itemCount > 0,
                'detail'  => $itemCount > 0 ? "{$itemsWithHead}/{$itemCount} linked to a head" : null,
            ],
            [
                'key'     => 'head_windows',
                'label'   => 'Head dates & head fees',
                'hint'    => 'Registration/competition windows per head; default & extra item fee per head.',
                'href'    => "{$base}/competition",
                'done'    => $headsWithDates > 0 || $headsWithFees > 0,
                'detail'  => $headCount > 0 ? "{$headsWithDates} head(s) with dates · {$headsWithFees} with fees" : null,
            ],
            [
                'key'     => 'item_fees',
                'label'   => 'Item-wise fee overrides',
                'hint'    => 'Optional per-item fee on top of head defaults — set in competition hub or items catalog.',
                'href'    => "{$base}/competition",
                'done'    => $itemsWithFees > 0 || $headsWithFees > 0,
                'detail'  => $itemsWithFees > 0 ? "{$itemsWithFees} item(s) with custom fee" : 'Using head/event defaults',
            ],
            [
                'key'     => 'rank_points',
                'label'   => 'Rank points master',
                'hint'    => 'Fixed team points per rank (1st, 2nd…). Ties share the same rank and points.',
                'href'    => "{$base}/settings/points",
                'done'    => $rankPointCount > 0,
            ],
            [
                'key'     => 'registration',
                'label'   => 'Registration windows',
                'hint'    => 'Event-level vs per-head registration open/close; student self-register.',
                'href'    => "{$base}/settings/registration",
                'done'    => filled($event->event_reg_start) || filled($event->event_reg_end) || $headsWithDates > 0,
            ],
            [
                'key'     => 'numbering',
                'label'   => 'Chest & item numbering',
                'hint'    => 'Chest number ranges, auto-assign on approval.',
                'href'    => "{$base}/settings/numbering",
                'done'    => true,
            ],
        ];

        $tenantMasters = [
            [
                'label' => 'Sports items master',
                'hint'  => 'Sahodaya-wide catalog — enable items and default fees before loading into events.',
                'href'  => "{$tenantBase}/sports/catalog?event_id={$event->id}",
            ],
            [
                'label' => 'Item heads catalog',
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

        $doneCount = collect($checklist)->where('done', true)->count();

        return $this->inertia('Sahodaya/Events/SportsSetup', $this->withEventActivity($event, FestPageActivity::SETTINGS, [
            'event'          => $event->only('id', 'title', 'status', 'event_type', 'registration_open', 'registration_close', 'results_published'),
            'checklist'      => $checklist,
            'checklistProgress' => [
                'done'  => $doneCount,
                'total' => count($checklist),
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
        ]));
    }

    private function eventQuery(int $eventId): string
    {
        return "?event_id={$eventId}";
    }
}
<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestMarkCoordinatorAccess;
use App\Services\Events\FestMarkEntryScopeService;
use App\Services\Events\FestMarkSaveService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestSportsAutoRankService;
use App\Services\Events\PortalEventHeadNavService;
use Illuminate\Http\Request;

class FestMarkCoordinatorController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();
        $user = $request->user();
        $assignedIds = FestMarkCoordinatorAccess::assignedEventIds($user, $tenantId);

        $eventsQuery = FestEvent::where('tenant_id', $tenantId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->orderByDesc('event_start');

        if ($assignedIds !== null) {
            $eventsQuery->whereIn('id', $assignedIds ?: [0]);
        }

        $events = $eventsQuery->get()->map(function (FestEvent $event) {
            $participantCount = FestParticipant::query()
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->whereNotIn('status', ['rejected', 'withdrawn']))
                ->count();

            $marksEntered = FestMark::where('event_id', $event->id)
                ->where(function ($q) {
                    $q->whereNotNull('position')->orWhereNotNull('score')->orWhereNotNull('grade');
                })
                ->count();

            return [
                'id'            => $event->id,
                'title'         => $event->title,
                'status'        => $event->status,
                'level_round'   => $event->level_round,
                'participants'  => $participantCount,
                'marks_entered' => $marksEntered,
                'pending'       => max(0, $participantCount - $marksEntered),
            ];
        });

        return inertia('Portal/FestCoordinator/Dashboard', [
            'sahodaya' => $sahodaya->only('id', 'name'),
            'events'   => $events,
        ]);
    }

    public function marks(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_unless(FestMarkCoordinatorAccess::canAccessEvent($request->user(), $event), 403, 'You\'re not assigned as a mark coordinator for this event.');

        $event->load('items');

        $scope = app(FestMarkEntryScopeService::class);
        $registrations = $scope->scopedRegistrations($event, $request->user());
        $marks = $scope->officialMarks($event);

        $headNav = app(PortalEventHeadNavService::class);
        $headContext = $headNav->context($event, $request);
        $registrations = $headNav->filterRegistrations(
            $registrations,
            $headContext['selectedHeadId'],
            $headContext['selectedItemId'],
        );

        // Region/season hub: registrations/attendance live under child events — mirror
        // SahodayaAdmin\FestAttendanceController's reportableEventIds() expansion so a
        // hub-scoped coordinator sees attendance regardless of which specific child
        // event a participant's registration actually landed on.
        $attendance = FestAttendance::whereIn('event_id', $event->reportableEventIds())
            ->get()
            ->mapWithKeys(fn (FestAttendance $row) => [
                "{$row->item_id}-{$row->participant_id}" => ['status' => $row->status],
            ])
            ->all();

        return inertia('Portal/FestCoordinator/MarkEntry', [
            'sahodaya'      => Tenant::find($tenantId)?->only('id', 'name'),
            'event'         => $event,
            'registrations' => $registrations,
            'marks'         => $marks,
            'attendance'    => $attendance,
            'rankPoints'    => $event->event_type === 'sports'
                ? app(FestRankPointService::class)->listForEvent($event)
                : [],
            ...$headContext,
        ]);
    }

    public function storeAttendance(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_unless(FestMarkCoordinatorAccess::canAccessEvent($request->user(), $event), 403, 'You\'re not assigned as a mark coordinator for this event.');

        $data = $request->validate([
            'item_id'        => 'required|exists:fest_event_items,id',
            'participant_id' => 'required|exists:fest_participants,id',
            'status'         => 'required|in:present,absent',
        ]);

        // Cross-scope validation — see FestEventOpsController::storeAttendance() for why:
        // reads above now span the event's whole reportable family, so writes need the
        // same guard against an unrelated participant/item being posted here.
        $participant = FestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if(! in_array($participant->registration->event_id, $event->reportableEventIds(), true), 422, 'Participant does not belong to this event.');
        abort_if($participant->registration->item_id !== (int) $data['item_id'], 422, 'Participant does not belong to this item.');

        FestAttendance::updateOrCreate(
            ['item_id' => $data['item_id'], 'participant_id' => $data['participant_id']],
            [
                'event_id'  => $event->id,
                'status'    => $data['status'],
                'marked_by' => $request->user()->id,
                'marked_at' => now(),
            ]
        );

        return back()->with('success', 'Attendance saved.');
    }

    public function autoRankItem(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_unless(FestMarkCoordinatorAccess::canAccessEvent($request->user(), $event), 403, 'You\'re not assigned as a mark coordinator for this event.');
        abort_if($item->event_id !== $event->id, 404);
        abort_unless($event->event_type === 'sports', 422, 'Auto-rank applies to sports events only.');

        app(FestMarkEntryScopeService::class)->assertCanEnterMark($request->user(), $event, $item->id);

        $result = $ranker->rankItem($event, $item);

        return back()->with('success', "Auto-ranked {$result['ranked']} athlete(s) for {$result['item_title']}.");
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $event, FestMarkSaveService $markSave)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_unless(FestMarkCoordinatorAccess::canAccessEvent($request->user(), $event), 403, 'You\'re not assigned as a mark coordinator for this event.');

        EventLifecycleGate::allowMarkEntry($event);

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => 'nullable|in:A,A+,B,C',
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
        ]);

        app(FestMarkEntryScopeService::class)->assertCanEnterMark($request->user(), $event, (int) $data['item_id']);

        $result = $markSave->save($event, $data, $request->user()->id);

        return back()->with('success', $result['message']);
    }
}

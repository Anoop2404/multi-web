<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\FestAppeal;
use App\Models\FestAttendance;
use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventContext;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestRegistrationApprovalService;
use App\Services\Events\FestRegistrationBulkService;
use App\Services\Events\FestRegistrationService;
use App\Services\Events\FestPublicVisibilityService;
use App\Services\Events\FestReportService;
use App\Services\Events\FestParticipantLookupService;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestMarkEntryScopeService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestSportsAutoRankService;
use App\Services\Events\PortalEventHeadNavService;
use Illuminate\Http\Request;

class FestEventOpsController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();
        $user = $request->user();

        $assignments = $this->assignmentsFor($user, $tenantId);
        $eventIds = $assignments->pluck('event_id')->unique();

        $events = FestEvent::whereIn('id', $eventIds)
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status', 'event_start']);

        $byEvent = $assignments->groupBy('event_id')->map(fn ($rows) => $rows->pluck('duty')->unique()->values());

        return inertia('Portal/FestOps/Dashboard', [
            'sahodaya'      => $sahodaya->only('id', 'name'),
            'events'        => $events,
            'dutiesByEvent' => $byEvent,
        ]);
    }

    public function event(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeAssignment($request, $event->id);

        $duties = $this->assignmentsFor($request->user(), $tenantId)
            ->where('event_id', $event->id)
            ->pluck('duty')
            ->unique()
            ->values();

        $sahodaya = Tenant::findOrFail($tenantId);

        return inertia('Portal/FestOps/Event', [
            'sahodaya' => $sahodaya->only('id', 'name'),
            'event'    => $event->only('id', 'title', 'status', 'event_start'),
            'duties'   => $duties,
        ]);
    }

    public function coordinator(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'coordinator');

        $stats = [
            'pending_registrations' => FestRegistration::where('event_id', $event->id)->where('status', 'submitted')->count(),
            'approved_registrations'=> FestRegistration::where('event_id', $event->id)->where('status', 'approved')->count(),
            'open_appeals'          => FestAppeal::where('event_id', $event->id)->where('status', 'pending')->count(),
            'schedule_entries'      => FestSchedule::where('event_id', $event->id)->count(),
            'staff_assignments'     => FestEventStaff::where('event_id', $event->id)->count(),
        ];

        return inertia('Portal/FestOps/Coordinator', [
            'sahodaya' => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'    => $event->only('id', 'title', 'status', 'event_start'),
            'stats'    => $stats,
            'duties'   => $this->userDuties($request, $tenantId, $event->id),
        ]);
    }

    public function registrations(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'registration');

        $registrations = FestRegistration::where('event_id', $event->id)
            ->with(['item.head', 'participants.student', 'participants.teacher'])
            ->latest()
            ->get()
            ->filter(fn (FestRegistration $reg) => $this->registrationVisibleToUser($request, $event, $reg))
            ->values();

        $headNav = app(PortalEventHeadNavService::class);
        $headContext = $headNav->context($event, $request);
        $registrations = $headNav->filterRegistrations(
            $registrations,
            $headContext['selectedHeadId'],
            $headContext['selectedItemId'],
        );

        $schools = Tenant::where('parent_id', $tenantId)->pluck('name', 'id');
        $feeRequired = app(FestSchoolEventFeeService::class)->feeRequired($event);

        return inertia('Portal/FestOps/Registrations', [
            'sahodaya'      => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'         => $event->only('id', 'title', 'event_type'),
            'registrations' => $registrations,
            'schools'       => $schools,
            'feeRequired'   => $feeRequired,
            'duties'        => $this->userDuties($request, $tenantId, $event->id),
            ...$headContext,
        ]);
    }

    public function approveRegistration(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestRegistration $registration,
        PlatformAuditLogger $audit,
    ) {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($registration->event_id !== $event->id, 403);
        $this->authorizeDuty($request, $event->id, 'registration');

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'));

        $feeService = app(FestSchoolEventFeeService::class);
        if ($feeService->feeRequired($event)) {
            abort_unless(
                $feeService->isPaidForRegistration($event, $registration),
                422,
                'The Event Head fee for this registration must be approved before registration approval.'
            );
        }

        app(FestRegistrationApprovalService::class)->approve($registration->load(['participants', 'item', 'event']));

        app(FestEventNotifier::class)->registrationApproved($registration);
        $audit->festRegistrationApproved($registration);

        return back()->with('success', 'Registration approved.');
    }

    public function rejectRegistration(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestRegistration $registration,
        PlatformAuditLogger $audit,
    ) {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($registration->event_id !== $event->id, 403);
        $this->authorizeDuty($request, $event->id, 'registration');

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'));

        $reason = $request->string('rejection_reason', '')->toString();

        $registration->update([
            'status'              => 'rejected',
            'rejection_reason'    => $reason ?: null,
            'rejected_at'         => now(),
            'rejected_by_user_id' => $request->user()->id,
        ]);
        app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);
        app(FestEventNotifier::class)->registrationRejected($registration, $reason);
        $audit->festRegistrationRejected($registration);

        return back()->with('success', 'Registration rejected.');
    }

    public function cancelRegistration(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestRegistration $registration,
    ) {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($registration->event_id !== $event->id, 403);
        $this->authorizeDuty($request, $event->id, 'registration');

        abort_unless(
            app(FestRegistrationService::class)->canAdminCancel($registration, $event),
            422,
            'Cannot cancel — results are published or the fee for this registration has already been paid and approved.'
        );

        app(FestRegistrationService::class)->cancel($registration, $event);

        return back()->with('success', 'Registration cancelled.');
    }

    public function bulkApproveRegistrations(Request $request, string $tenantId, FestEvent $event, FestRegistrationBulkService $bulk)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'registration');

        $data = $request->validate([
            'registration_ids'   => 'nullable|array',
            'registration_ids.*' => 'integer|exists:fest_registrations,id',
            'school_id'          => 'nullable|exists:tenants,id',
            'override_lifecycle' => 'nullable|boolean',
        ]);

        $result = $bulk->approveMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
        );

        return back()->with('success', "Approved {$result['approved']} registration(s).")->with('importErrors', array_slice($result['errors'], 0, 20));
    }

    public function bulkRejectRegistrations(Request $request, string $tenantId, FestEvent $event, FestRegistrationBulkService $bulk)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'registration');

        $data = $request->validate([
            'registration_ids'   => 'nullable|array',
            'registration_ids.*' => 'integer|exists:fest_registrations,id',
            'school_id'          => 'nullable|exists:tenants,id',
            'override_lifecycle' => 'nullable|boolean',
        ]);

        $result = $bulk->rejectMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
        );

        return back()->with('success', "Rejected {$result['rejected']} registration(s).");
    }

    public function appeals(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'appeals');

        $appeals = FestAppeal::where('event_id', $event->id)
            ->with(['participant.student', 'participant.registration.item'])
            ->latest()
            ->get();

        return inertia('Portal/FestOps/Appeals', [
            'sahodaya' => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'    => $event->only('id', 'title'),
            'appeals'  => $appeals,
            'duties'   => $this->userDuties($request, $tenantId, $event->id),
        ]);
    }

    public function resolveAppeal(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestAppeal $appeal,
        PlatformAuditLogger $audit,
    ) {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($appeal->event_id !== $event->id, 403);
        $this->authorizeDuty($request, $event->id, 'appeals');

        $data = $request->validate([
            'status'          => 'required|in:approved,rejected',
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $appeal->update([
            'status'              => $data['status'],
            'resolution_note'     => $data['resolution_note'] ?? null,
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at'         => now(),
        ]);

        $audit->festAppealResolved($appeal, $data['status']);

        return back()->with('success', 'Appeal '.$data['status'].'.');
    }

    public function certificates(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'certificates');

        $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id))
            ->pluck('id');

        $service = app(FestCertificateService::class);
        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->orderByDesc('generated_at')
            ->get()
            ->map(fn ($c) => array_merge(
                ['id' => $c->id, 'uuid' => $c->verification_uuid],
                $service->payloadFor($c)
            ));

        return inertia('Portal/FestOps/Certificates', [
            'sahodaya'     => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'        => $event->only('id', 'title'),
            'certificates' => $certificates,
            'duties'       => $this->userDuties($request, $tenantId, $event->id),
        ]);
    }

    public function stage(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'stage');

        $visibility = app(FestPublicVisibilityService::class);
        $allowedStageIds = $this->assignedStageIds($request, $event->id);

        $schedulesQuery = FestSchedule::where('event_id', $event->id)
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.item', 'participant.registration.event', 'festStage.venue']);

        if ($allowedStageIds !== null) {
            $schedulesQuery->whereIn('stage_id', $allowedStageIds);
        }

        $schedules = $schedulesQuery
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FestSchedule $s) => array_merge($s->toArray(), [
                'public' => $s->participant
                    ? $visibility->formatPublicParticipant($event, $s->participant, $s)
                    : null,
            ]));

        $assignedStages = $allowedStageIds === null
            ? null
            : FestStage::whereIn('id', $allowedStageIds)->orderBy('sort_order')->get(['id', 'name']);

        return inertia('Portal/FestOps/Stage', [
            'sahodaya'       => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'          => $event->only('id', 'title', 'chest_reveal_mode'),
            'schedules'      => $schedules,
            'assignedStages' => $assignedStages,
            'duties'         => $this->userDuties($request, $tenantId, $event->id),
        ]);
    }

    public function markStageCalled(Request $request, string $tenantId, FestEvent $event, FestSchedule $schedule)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($schedule->event_id !== $event->id, 404);
        $this->authorizeDuty($request, $event->id, 'stage');
        $this->authorizeScheduleStage($request, $event->id, $schedule);

        $data = $request->validate([
            'called' => 'required|boolean',
        ]);

        $schedule->update(['called_at' => $data['called'] ? now() : null]);

        if ($data['called'] && $schedule->participant_id) {
            $participant = FestParticipant::with(['registration.event', 'registration.item'])->find($schedule->participant_id);
            if ($participant) {
                app(\App\Services\Events\FestChestNumberService::class)->revealAtStageEntry($participant);
            }
        }

        return back()->with('success', $data['called'] ? 'Participant called and chest revealed.' : 'Call cleared.');
    }

    public function reorderStage(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'stage');

        $data = $request->validate([
            'order'   => 'required|array|min:1',
            'order.*' => 'integer|exists:fest_schedules,id',
        ]);

        $allowedStageIds = $this->assignedStageIds($request, $event->id);
        if ($allowedStageIds !== null) {
            $outOfScope = FestSchedule::where('event_id', $event->id)
                ->whereIn('id', $data['order'])
                ->where(function ($q) use ($allowedStageIds) {
                    $q->whereNotIn('stage_id', $allowedStageIds)->orWhereNull('stage_id');
                })
                ->exists();

            abort_if($outOfScope, 403, 'You can only reorder schedules on your assigned stage.');
        }

        foreach ($data['order'] as $index => $scheduleId) {
            FestSchedule::where('id', $scheduleId)
                ->where('event_id', $event->id)
                ->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Stage order updated.');
    }

    public function kitchen(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'food');

        $orders = FestCateringOrder::where('event_id', $event->id)
            ->orderBy('meal_date')
            ->orderBy('meal_type')
            ->get();

        $schools = Tenant::whereIn('id', $orders->pluck('school_id')->unique())->pluck('name', 'id');

        return inertia('Portal/FestOps/Kitchen', [
            'sahodaya' => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'    => $event->only('id', 'title'),
            'orders'   => $orders,
            'schools'  => $schools,
            'duties'   => $this->userDuties($request, $tenantId, $event->id),
        ]);
    }

    public function updateOrderStatus(Request $request, string $tenantId, FestEvent $event, FestCateringOrder $order)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($order->event_id !== $event->id, 404);
        $this->authorizeDuty($request, $event->id, 'food');

        $data = $request->validate([
            'status' => 'required|in:requested,confirmed,cancelled',
        ]);

        $order->update(['status' => $data['status']]);

        return back()->with('success', 'Order status updated.');
    }

    public function attendance(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'attendance');

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn']))
            ->with(['registration.item', 'student', 'teacher'])
            ->get();

        $attendance = FestAttendance::where('event_id', $event->id)
            ->get()
            ->keyBy(fn ($a) => $a->item_id.'-'.$a->participant_id);

        return inertia('Portal/FestOps/Attendance', [
            'sahodaya'     => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'        => $event->only('id', 'title'),
            'participants' => $participants,
            'attendance'   => $attendance,
            'duties'       => $this->userDuties($request, $tenantId, $event->id),
        ]);
    }

    public function storeAttendance(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);

        if ($event->event_type === 'sports') {
            $this->authorizeDutyAny($request, $event->id, ['attendance', 'marks']);
        } else {
            $this->authorizeDuty($request, $event->id, 'attendance');
        }

        $data = $request->validate([
            'item_id'        => 'required|exists:fest_event_items,id',
            'participant_id' => 'required|exists:fest_participants,id',
            'status'         => 'required|in:present,absent',
        ]);

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

    public function substituteRegistration(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestRegistration $registration,
        FestParticipant $performer,
        FestParticipant $standby,
    ) {
        abort_if($event->tenant_id !== $tenantId, 403);
        abort_if($registration->event_id !== $event->id, 403);
        abort_if($performer->registration_id !== $registration->id || $standby->registration_id !== $registration->id, 403);
        $this->authorizeDuty($request, $event->id, 'registration');

        app(FestRegistrationService::class)->substitutePerformer($performer, $standby);

        return back()->with('success', 'Participant substituted.');
    }

    public function participantSearch(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeAssignment($request, $event->id);

        $q = trim((string) $request->query('q', ''));
        $results = [];

        if (strlen($q) >= 2) {
            $lookup = app(FestParticipantLookupService::class);
            $participant = $lookup->resolveForEvent($event, [
                'reg_no'   => $q,
                'chest_no' => is_numeric($q) ? (int) $q : null,
            ]);

            if ($participant) {
                $participant->load(['student', 'teacher', 'registration.item', 'registration.school']);
                $results[] = [
                    'participant_id' => $participant->id,
                    'name'           => $participant->student?->name ?? $participant->teacher?->name,
                    'reg_no'         => $participant->student?->reg_no ?? $participant->teacher?->reg_no,
                    'chest_no'       => $participant->chest_no,
                    'item'           => $participant->registration?->item?->title,
                    'school'         => $participant->registration?->school?->name,
                    'status'         => $participant->registration?->status,
                ];
            } else {
                $participants = FestParticipant::whereHas('registration', fn ($r) => $r->where('event_id', $event->id))
                    ->where(function ($query) use ($q) {
                        $query->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('reg_no', 'like', "%{$q}%"))
                            ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$q}%")->orWhere('reg_no', 'like', "%{$q}%"));
                    })
                    ->with(['student', 'teacher', 'registration.item', 'registration.school'])
                    ->limit(20)
                    ->get();

                foreach ($participants as $p) {
                    $results[] = [
                        'participant_id' => $p->id,
                        'name'           => $p->student?->name ?? $p->teacher?->name,
                        'reg_no'         => $p->student?->reg_no ?? $p->teacher?->reg_no,
                        'chest_no'       => $p->chest_no,
                        'item'           => $p->registration?->item?->title,
                        'school'         => $p->registration?->school?->name,
                        'status'         => $p->registration?->status,
                    ];
                }
            }
        }

        return inertia('Portal/FestOps/ParticipantSearch', [
            'sahodaya' => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'event'    => $event->only('id', 'title'),
            'query'    => $q,
            'results'  => $results,
            'duties'   => $this->userDuties($request, $tenantId, $event->id),
        ]);
    }

    public function marks(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'marks');

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

        $attendance = FestAttendance::where('event_id', $event->id)
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
            'festOpsBase'   => "/portal/fest-ops/{$tenantId}/events/{$event->id}",
            ...$headContext,
        ]);
    }

    public function autoRankItem(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'marks');
        abort_if($item->event_id !== $event->id, 404);
        abort_unless($event->event_type === 'sports', 422, 'Auto-rank applies to sports events only.');

        app(FestMarkEntryScopeService::class)->assertCanEnterMark($request->user(), $event, $item->id);

        $result = $ranker->rankItem($event, $item);

        return back()->with('success', "Auto-ranked {$result['ranked']} athlete(s) for {$result['item_title']}.");
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $event, \App\Services\Events\FestMarkSaveService $markSave)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeDuty($request, $event->id, 'marks');

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

    public function admitCard(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->authorizeAssignment($request, $event->id);

        $participantId = $request->query('participant_id');
        abort_unless($participantId, 422, 'participant_id required');

        $participant = FestParticipant::with('registration')->findOrFail($participantId);
        abort_if($participant->registration?->event_id !== $event->id, 403);

        $schoolId = $participant->registration?->school_id;
        $params = ['participant_id' => $participantId, 'school_id' => $schoolId];
        if ($participant->student_id) {
            $params['student_id'] = $participant->student_id;
        } elseif ($participant->teacher_id) {
            $params['teacher_id'] = $participant->teacher_id;
        }

        return (new FestReportService($event))->downloadAdmitCards(Request::create('/', 'GET', $params));
    }

    /** @return list<int>|null null = all stages */
    private function assignedStageIds(Request $request, int $eventId): ?array
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasRole('sahodaya_admin')) {
            return null;
        }

        $assignments = FestEventStaff::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->where('duty', 'stage')
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $scoped = $assignments->whereNotNull('stage_id')->pluck('stage_id')->unique()->values()->all();

        return $scoped === [] ? null : $scoped;
    }

    private function authorizeScheduleStage(Request $request, int $eventId, FestSchedule $schedule): void
    {
        $allowedStageIds = $this->assignedStageIds($request, $eventId);
        if ($allowedStageIds === null) {
            return;
        }

        abort_unless(
            $schedule->stage_id && in_array($schedule->stage_id, $allowedStageIds, true),
            403,
            'This schedule slot is not on your assigned stage.'
        );
    }

    private function authorizeAssignment(Request $request, int $eventId): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasRole('sahodaya_admin')) {
            return;
        }

        $has = FestEventStaff::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($has, 403);
    }

    private function authorizeDuty(Request $request, int $eventId, string $duty): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasRole('sahodaya_admin')) {
            return;
        }

        $has = FestEventStaff::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->where('duty', $duty)
            ->exists();

        abort_unless($has, 403);
    }

    /** @param  list<string>  $duties */
    private function authorizeDutyAny(Request $request, int $eventId, array $duties): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasRole('sahodaya_admin')) {
            return;
        }

        $has = FestEventStaff::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->whereIn('duty', $duties)
            ->exists();

        abort_unless($has, 403);
    }

    private function assignmentsFor($user, string $tenantId)
    {
        if ($user->isSuperAdmin() || ($user->hasRole('sahodaya_admin') && $user->tenant_id === $tenantId)) {
            return FestEventStaff::query()->get();
        }

        return FestEventStaff::where('user_id', $user->id)->get();
    }

    /** @return list<string> */
    private function userDuties(Request $request, string $tenantId, int $eventId): array
    {
        return $this->assignmentsFor($request->user(), $tenantId)
            ->where('event_id', $eventId)
            ->pluck('duty')
            ->unique()
            ->values()
            ->all();
    }

    private function registrationVisibleToUser(Request $request, FestEvent $event, FestRegistration $registration): bool
    {
        return app(FestMarkEntryScopeService::class)->userCanAccessRegistration(
            $request->user(),
            $event,
            $registration,
        );
    }
}

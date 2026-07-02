<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\FestAppeal;
use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\InAppNotification;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Models\FestSchedule;
use App\Services\Events\FestPublicVisibilityService;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestReportService;
use App\Support\Mcq\McqResultPresenter;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);

        $registrations = FestRegistration::where('school_id', $tenantId)
            ->whereHas('participants', fn ($q) => $q->where('student_id', $student->id))
            ->with(['event', 'item'])
            ->latest()
            ->limit(10)
            ->get();

        $mcqExams = McqRegistration::where('student_id', $student->id)
            ->with(['exam', 'mark'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (McqRegistration $reg) {
                $exam = $reg->exam;
                if (! $exam) {
                    return null;
                }

                return array_merge(
                    McqResultPresenter::forExamList($exam, $reg),
                    [
                        'show_hall_ticket' => (bool) $reg->hall_ticket_no,
                        'can_take_online'  => $exam->isOnlineDelivery()
                            && in_array($exam->status, ['published', 'ongoing'], true)
                            && $reg->status !== 'submitted'
                            && $reg->attendance_status !== 'absent'
                            && app(\App\Services\Mcq\McqExamSessionService::class)->canTakeOnline($reg),
                        'delivery_mode'      => $exam->delivery_mode ?? 'offline',
                        'registration_route_id' => $reg->id,
                    ]
                );
            })
            ->filter()
            ->values();

        $festResults = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId))
            ->whereHas('registration.event', fn ($q) => $q->where('results_published', true))
            ->with(['registration.event', 'registration.item', 'mark'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (FestParticipant $p) => [
                'event_title' => $p->registration?->event?->title,
                'item_title'  => $p->registration?->item?->title,
                'grade'       => $p->mark?->grade,
                'position'    => $p->mark?->position,
                'score'       => $p->mark?->score,
                'chest_no'    => $p->chest_no,
            ]);

        $festCertificates = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId))
            ->pluck('id');

        $certService = app(FestCertificateService::class);
        $festCerts = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $festCertificates)
            ->orderByDesc('generated_at')
            ->limit(10)
            ->get()
            ->map(fn (Certificate $c) => array_merge(
                ['uuid' => $c->verification_uuid],
                $certService->payloadFor($c)
            ));

        $notifications = InAppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        $festDaySlots = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->whereHas('registration.event', fn ($q) => $q->whereIn('status', ['ongoing', 'registration_open', 'published']))
            ->with(['registration.event', 'registration.item'])
            ->get()
            ->map(function (FestParticipant $p) {
                $schedule = FestSchedule::where('participant_id', $p->id)->first();

                return [
                    'event_title'    => $p->registration?->event?->title,
                    'item_title'     => $p->registration?->item?->title,
                    'chest_no'       => $p->chest_no,
                    'level_reg'      => $p->level_registration_number,
                    'order'          => $schedule?->sort_order,
                    'scheduled_at'   => $schedule?->scheduled_at?->toIso8601String(),
                    'stage'          => $schedule?->stage,
                    'event_status'   => $p->registration?->event?->status,
                ];
            })
            ->filter(fn ($row) => $row['event_title'])
            ->values();

        $participantIds = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId))
            ->pluck('id');

        $festAppeals = FestAppeal::whereIn('participant_id', $participantIds)
            ->with(['participant.registration.event', 'participant.registration.item'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (FestAppeal $a) => [
                'event_title' => $a->participant?->registration?->event?->title,
                'item_title'  => $a->participant?->registration?->item?->title,
                'status'      => $a->status,
                'reason'      => $a->reason,
                'resolution'  => $a->resolution_note,
                'submitted_at'=> $a->created_at?->toIso8601String(),
            ]);

        $appealableParticipants = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->whereHas('registration.event', fn ($q) => $q->where('appeals_open', true))
            ->with(['registration.event:id,title,appeals_open', 'registration.item:id,title'])
            ->get()
            ->map(fn (FestParticipant $p) => [
                'participant_id' => $p->id,
                'event_id'       => $p->registration?->event_id,
                'event_title'    => $p->registration?->event?->title,
                'item_title'     => $p->registration?->item?->title,
            ]);

        $sahodayaId = $school->parent_id;
        $upcomingEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['registration_open', 'published', 'ongoing'])
            ->orderBy('event_start')
            ->limit(5)
            ->get(['id', 'title', 'event_type', 'event_start', 'status'])
            ->map(fn (FestEvent $e) => [
                'id'          => $e->id,
                'title'       => $e->title,
                'event_type'  => $e->event_type,
                'event_start' => $e->event_start?->toDateString(),
                'status'      => $e->status,
            ]);

        return inertia('Portal/Student/Dashboard', [
            'school'        => $school->only('id', 'name'),
            'student'       => $student->only('id', 'name', 'reg_no', 'email'),
            'logoUrl'       => TenantBranding::logoUrl($school),
            'registrations' => $registrations,
            'upcomingEvents'=> $upcomingEvents,
            'festResults'   => $festResults,
            'festDaySlots'  => $festDaySlots,
            'festCerts'     => $festCerts,
            'festAppeals'   => $festAppeals,
            'appealableParticipants' => $appealableParticipants,
            'mcqExams'      => $mcqExams,
            'notifications' => $notifications,
            'admitCardEvents' => $this->admitCardEvents($student, $tenantId),
        ]);
    }

    public function sportsResults(Request $request, string $tenantId)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);

        $results = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId))
            ->whereHas('registration.event', fn ($q) => $q
                ->where('event_type', 'sports')
                ->where('results_published', true))
            ->with(['registration.event', 'registration.item', 'mark'])
            ->latest()
            ->get()
            ->map(function (FestParticipant $p) {
                $mark = $p->mark;
                $record = $mark
                    ? FestAthleticRecord::where('source_mark_id', $mark->id)->first()
                    : null;

                return [
                    'event_title' => $p->registration?->event?->title,
                    'item_title'  => $p->registration?->item?->title,
                    'grade'       => $mark?->grade,
                    'position'    => $mark?->position,
                    'score'       => $mark?->score,
                    'measurement' => $mark?->measurement_value
                        ? trim("{$mark->measurement_value} {$mark->measurement_unit}")
                        : null,
                    'record_label' => $record
                        ? "Record: {$record->record_value} {$record->record_unit}"
                        : null,
                ];
            })
            ->filter(fn (array $r) => $r['event_title'])
            ->values();

        return inertia('Portal/Student/SportsResults', [
            'school'  => $school->only('id', 'name'),
            'student' => $student->only('id', 'name', 'reg_no'),
            'results' => $results,
        ]);
    }

    public function admitCard(Request $request, string $tenantId, FestEvent $event)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($event->tenant_id !== Tenant::findOrFail($tenantId)->parent_id, 403);

        $hasEntry = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->exists();

        abort_unless($hasEntry, 403, 'No approved fest entry for this event.');

        return (new FestReportService($event))->downloadAdmitCards(
            Request::create('/', 'GET', [
                'school_id'  => $tenantId,
                'student_id' => $student->id,
            ])
        );
    }

    /** @return list<array<string, mixed>> */
    private function admitCardEvents($student, string $tenantId): array
    {
        return FestEvent::where('tenant_id', Tenant::find($tenantId)?->parent_id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->whereHas('registrations', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved')
                ->whereHas('participants', fn ($p) => $p->where('student_id', $student->id)))
            ->orderByDesc('event_start')
            ->get(['id', 'title'])
            ->map(fn (FestEvent $e) => ['id' => $e->id, 'title' => $e->title])
            ->values()
            ->all();
    }
}

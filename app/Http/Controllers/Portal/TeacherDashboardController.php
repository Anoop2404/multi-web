<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestSchoolEventFee;
use App\Models\InAppNotification;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestReportService;
use App\Services\Training\TrainingCertificateService;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);

        $training = TrainingRegistration::where('teacher_id', $teacher->id)
            ->with(['program.sessions', 'certificate', 'feeReceipt'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (TrainingRegistration $reg) {
                $sessions = $reg->program?->sessions ?? collect();
                $attendance = \App\Models\TrainingAttendance::where('registration_id', $reg->id)
                    ->get()
                    ->keyBy('session_id');

                return [
                    'id'       => $reg->id,
                    'status'   => $reg->status,
                    'program'  => $reg->program?->only('id', 'title', 'description'),
                    'feeReceipt' => $reg->feeReceipt?->only('status'),
                    'certificate_uuid' => $reg->certificate?->verification_uuid,
                    'sessions' => $sessions->map(fn ($s) => [
                        'id'           => $s->id,
                        'title'        => $s->title,
                        'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                        'venue'        => $s->venue,
                        'attendance'   => $attendance->get($s->id)?->status,
                    ])->values(),
                ];
            });

        $mcqBanks = \App\Models\McqQuestionBank::where('school_id', $tenantId)
            ->where('created_by_user_id', $request->user()->id)
            ->withCount('questions')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'exam_id']);

        $festRegistrations = FestRegistration::where('school_id', $tenantId)
            ->whereHas('participants', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->with(['event', 'item'])
            ->latest()
            ->limit(10)
            ->get();

        $festResults = FestParticipant::where('teacher_id', $teacher->id)
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

        $participantIds = FestParticipant::where('teacher_id', $teacher->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId))
            ->pluck('id');

        $festCerts = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->orderByDesc('generated_at')
            ->limit(10)
            ->get()
            ->map(fn (Certificate $c) => array_merge(
                ['uuid' => $c->verification_uuid],
                app(FestCertificateService::class)->payloadFor($c)
            ));

        $festDaySlots = FestParticipant::where('teacher_id', $teacher->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->whereHas('registration.event', fn ($q) => $q->whereIn('status', ['ongoing', 'registration_open', 'published']))
            ->with(['registration.event', 'registration.item'])
            ->get()
            ->map(function (FestParticipant $p) {
                $schedule = FestSchedule::where('participant_id', $p->id)->first();

                return [
                    'event_title'  => $p->registration?->event?->title,
                    'item_title'   => $p->registration?->item?->title,
                    'chest_no'     => $p->chest_no,
                    'level_reg'    => $p->level_registration_number,
                    'scheduled_at' => $schedule?->scheduled_at?->toIso8601String(),
                    'stage'        => $schedule?->stage,
                ];
            })
            ->filter(fn ($row) => $row['event_title'])
            ->values();

        $admitCardEvents = FestEvent::where('tenant_id', $school->parent_id)
            ->where('event_type', 'teacher_fest')
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->whereHas('registrations', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved')
                ->whereHas('participants', fn ($p) => $p->where('teacher_id', $teacher->id)))
            ->orderByDesc('event_start')
            ->get(['id', 'title'])
            ->map(fn (FestEvent $e) => ['id' => $e->id, 'title' => $e->title])
            ->values();

        $notifications = InAppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

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

        $festFees = FestSchoolEventFee::where('school_id', $tenantId)
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $school->parent_id))
            ->with('event:id,title')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (FestSchoolEventFee $f) => [
                'event_title' => $f->event?->title,
                'total_due'   => $f->total_due,
                'status'      => $f->status,
            ]);

        $appealableParticipants = FestParticipant::where('teacher_id', $teacher->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->whereHas('registration.event', fn ($q) => $q->where('appeals_open', true))
            ->with(['registration.event:id,title', 'registration.item:id,title'])
            ->get()
            ->map(fn (FestParticipant $p) => [
                'participant_id' => $p->id,
                'event_id'       => $p->registration?->event_id,
                'event_title'    => $p->registration?->event?->title,
                'item_title'     => $p->registration?->item?->title,
            ]);

        return inertia('Portal/Teacher/Dashboard', [
            'school'            => $school->only('id', 'name'),
            'teacher'           => $teacher->only('id', 'name', 'reg_no', 'email', 'designation'),
            'training'          => $training,
            'mcqBanks'          => $mcqBanks,
            'festRegistrations' => $festRegistrations,
            'festResults'       => $festResults,
            'festDaySlots'      => $festDaySlots,
            'festCerts'         => $festCerts,
            'festAppeals'       => $festAppeals,
            'festFees'          => $festFees,
            'appealableParticipants' => $appealableParticipants,
            'admitCardEvents'   => $admitCardEvents,
            'notifications'     => $notifications,
        ]);
    }

    public function admitCard(Request $request, string $tenantId, FestEvent $event)
    {
        $teacher = $request->attributes->get('portalTeacher');
        abort_if($event->tenant_id !== Tenant::findOrFail($tenantId)->parent_id, 403);

        $hasEntry = FestParticipant::where('teacher_id', $teacher->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->exists();

        abort_unless($hasEntry, 403, 'No approved fest entry for this event.');

        return (new FestReportService($event))->downloadAdmitCards(
            Request::create('/', 'GET', [
                'school_id'  => $tenantId,
                'teacher_id' => $teacher->id,
            ])
        );
    }

    public function trainingCertificate(Request $request, string $tenantId, TrainingRegistration $registration)
    {
        $teacher = $request->attributes->get('portalTeacher');
        abort_if($registration->teacher_id !== $teacher->id, 403);

        $certificate = Certificate::where('entity_type', TrainingRegistration::class)
            ->where('entity_id', $registration->id)
            ->first();

        if (! $certificate) {
            app(TrainingCertificateService::class)->assertEligible($registration);
            $certificate = app(TrainingCertificateService::class)->issue($registration);
        }

        $registration->load(['program', 'teacher']);
        $sahodaya = Tenant::findOrFail($registration->program->tenant_id);
        $fieldValues = app(TrainingCertificateService::class)->resolveFieldValues($registration, $sahodaya);

        return view('training.certificate', [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'       => $sahodaya,
            'fieldValues'    => $fieldValues,
        ]);
    }
}

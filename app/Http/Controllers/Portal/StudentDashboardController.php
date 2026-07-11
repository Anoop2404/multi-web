<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\DownloadsStudentFestIdCard;
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
use App\Services\Students\StudentSportsProfileService;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    use DownloadsStudentFestIdCard;

    public function festSchedule(Request $request, string $tenantId)
    {
        [$student, $school] = $this->portalContext($request, $tenantId);

        return inertia('Portal/Student/FestSchedule', [
            'school'       => $school->only('id', 'name'),
            'student'      => $student->only('id', 'name', 'reg_no'),
            'festDaySlots' => $this->festDaySlots($student, $tenantId),
        ]);
    }

    public function festResultsPage(Request $request, string $tenantId)
    {
        [$student, $school] = $this->portalContext($request, $tenantId);

        return inertia('Portal/Student/FestResults', [
            'school'      => $school->only('id', 'name'),
            'student'     => $student->only('id', 'name', 'reg_no'),
            'festResults' => $this->festResults($student, $tenantId),
        ]);
    }

    public function festCertificates(Request $request, string $tenantId)
    {
        [$student, $school] = $this->portalContext($request, $tenantId);

        return inertia('Portal/Student/FestCertificates', [
            'school'    => $school->only('id', 'name'),
            'student'   => $student->only('id', 'name', 'reg_no'),
            'festCerts' => $this->festCertificatesFor($student, $tenantId),
        ]);
    }

    public function mcqHub(Request $request, string $tenantId)
    {
        [$student, $school] = $this->portalContext($request, $tenantId);

        return inertia('Portal/Student/McqHub', [
            'school'   => $school->only('id', 'name'),
            'student'  => $student->only('id', 'name', 'reg_no'),
            'mcqExams' => $this->mcqExamsFor($student),
        ]);
    }

    public function index(Request $request, string $tenantId)
    {
        [$student, $school] = $this->portalContext($request, $tenantId);

        $registrations = FestRegistration::where('school_id', $tenantId)
            ->whereHas('participants', fn ($q) => $q->where('student_id', $student->id))
            ->with(['event', 'item', 'participants' => fn ($q) => $q->where('student_id', $student->id)])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (FestRegistration $r) => [
                'id'       => $r->id,
                'status'   => $r->status,
                'event'    => $r->event?->only('id', 'title'),
                'item'     => $r->item?->only('id', 'title'),
                'chest_no' => $r->participants->first()?->chest_no,
            ]);

        $mcqExams = $this->mcqExamsFor($student);

        $festResults = $this->festResults($student, $tenantId);
        $festCerts = $this->festCertificatesFor($student, $tenantId);

        $notifications = InAppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        $festDaySlots = $this->festDaySlots($student, $tenantId);

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

        $sportsProfile = app(StudentSportsProfileService::class)->forStudent($student, $tenantId, 'portal');

        return inertia('Portal/Student/Dashboard', [
            'school'        => $school->only('id', 'name'),
            'student'       => $student->only('id', 'name', 'reg_no', 'email'),
            'logoUrl'       => TenantBranding::logoUrl($school),
            'registrations' => $registrations,
            'sportsProfile' => $sportsProfile,
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
            ->with(['registration.event', 'registration.item.head', 'mark'])
            ->latest()
            ->get()
            ->map(function (FestParticipant $p) {
                $mark = $p->mark;
                $record = $mark
                    ? FestAthleticRecord::where('source_mark_id', $mark->id)->first()
                    : null;

                return [
                    'event_title' => $p->registration?->event?->title,
                    'head_name'   => $p->registration?->item?->head?->name,
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

    public function festIdCard(Request $request, string $tenantId, FestEvent $event)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);

        return $this->studentFestIdCardResponse($request, $event, $student, $school);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function mcqExamsFor($student)
    {
        return McqRegistration::where('student_id', $student->id)
            ->with(['exam', 'mark'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (McqRegistration $reg) {
                $exam = $reg->exam;
                if (! $exam) {
                    return null;
                }

                return array_merge(
                    McqResultPresenter::forExamList($exam, $reg),
                    [
                        'id'               => $reg->id,
                        'exam'             => $exam->only('id', 'title'),
                        'lifecycle_status' => \App\Support\Mcq\McqRegistrationStatusPresenter::forRegistration($reg, $exam),
                        'show_results'     => (bool) $exam->results_published,
                        'show_hall_ticket' => (bool) $reg->hall_ticket_no,
                        'show_certificate' => (bool) $exam->results_published
                            && $reg->status === 'submitted'
                            && $reg->attendance_status !== 'absent'
                            && $reg->mark,
                        'certificate_url'  => (bool) $exam->results_published
                            && $reg->status === 'submitted'
                            && $reg->attendance_status !== 'absent'
                            && $reg->mark
                            ? "/portal/student/{$reg->school_id}/mcq/{$reg->id}/certificate"
                            : null,
                        'invoice_url' => $exam->hasFee()
                            ? "/portal/student/{$reg->school_id}/mcq/{$reg->id}/invoice"
                            : null,
                        'can_take_online'  => $exam->isOnlineDelivery()
                            && in_array($exam->status, ['published', 'ongoing'], true)
                            && $reg->status !== 'submitted'
                            && $reg->attendance_status !== 'absent'
                            && app(\App\Services\Mcq\McqExamSessionService::class)->canTakeOnline($reg),
                        'delivery_mode'         => $exam->delivery_mode ?? 'offline',
                        'registration_route_id' => $reg->id,
                    ]
                );
            })
            ->filter()
            ->values();
    }

    /** @return array{0: \App\Models\Student, 1: Tenant} */
    private function portalContext(Request $request, string $tenantId): array
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);

        return [$student, $school];
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function festDaySlots($student, string $tenantId)
    {
        return FestParticipant::where('student_id', $student->id)
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
                    'order'        => $schedule?->sort_order,
                    'scheduled_at' => $schedule?->scheduled_at?->toIso8601String(),
                    'stage'        => $schedule?->stage,
                    'event_status' => $p->registration?->event?->status,
                ];
            })
            ->filter(fn ($row) => $row['event_title'])
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function festResults($student, string $tenantId)
    {
        return FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId))
            ->whereHas('registration.event', fn ($q) => $q->where('results_published', true))
            ->with(['registration.event', 'registration.item', 'mark'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (FestParticipant $p) => [
                'event_title' => $p->registration?->event?->title,
                'item_title'  => $p->registration?->item?->title,
                'grade'       => $p->mark?->grade,
                'position'    => $p->mark?->position,
                'score'       => $p->mark?->score,
                'chest_no'    => $p->chest_no,
            ]);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function festCertificatesFor($student, string $tenantId)
    {
        $participantIds = FestParticipant::where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId))
            ->pluck('id');

        $certService = app(FestCertificateService::class);

        return Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->orderByDesc('generated_at')
            ->limit(50)
            ->get()
            ->map(fn (Certificate $c) => array_merge(
                ['uuid' => $c->verification_uuid],
                $certService->payloadFor($c)
            ));
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

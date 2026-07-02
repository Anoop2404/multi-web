<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestReportService;
use Illuminate\Http\Request;

class GroupAdminController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $user = $request->user();
        $school = Tenant::findOrFail($tenantId);

        // group_admin users are associated with a school (tenant_id = school id)
        // They can view students in the classes assigned to them.
        $classIds = $this->assignedClassIds($user, $tenantId);

        $classes = SchoolClass::where('tenant_id', $tenantId)
            ->when($classIds !== null, fn ($q) => $q->whereIn('id', $classIds))
            ->active()
            ->with('classCategory')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $studentCount = Student::where('tenant_id', $tenantId)
            ->when($classIds !== null, fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->active()
            ->count();

        return inertia('Portal/Group/Dashboard', [
            'school'       => $school->only('id', 'name', 'school_prefix'),
            'classes'      => $classes,
            'studentCount' => $studentCount,
            'user'         => $user->only('id', 'name', 'email'),
        ]);
    }

    public function students(Request $request, string $tenantId)
    {
        $user = $request->user();
        $classIds = $this->assignedClassIds($user, $tenantId);

        $students = Student::where('tenant_id', $tenantId)
            ->when($classIds !== null, fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->active()
            ->with('schoolClass.classCategory')
            ->orderBy('school_class_id')
            ->orderBy('name')
            ->get(['id', 'name', 'reg_no', 'school_class_id', 'roll_number', 'gender']);

        return inertia('Portal/Group/Students', [
            'tenantId' => $tenantId,
            'students' => $students,
        ]);
    }

    public function festRegistrations(Request $request, string $tenantId)
    {
        $classIds = $this->assignedClassIds($request->user(), $tenantId);
        $school = Tenant::findOrFail($tenantId);
        $sahodayaId = $school->parent_id;

        $studentIds = Student::where('tenant_id', $tenantId)
            ->when($classIds !== null, fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->active()
            ->pluck('id');

        $registrations = FestRegistration::where('school_id', $tenantId)
            ->whereHas('participants', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->with(['event:id,title,event_type,status', 'item:id,title', 'participants.student:id,name,reg_no'])
            ->latest('submitted_at')
            ->limit(100)
            ->get()
            ->map(fn (FestRegistration $reg) => [
                'id'           => $reg->id,
                'event_title'  => $reg->event?->title,
                'event_type'   => $reg->event?->event_type,
                'item_title'   => $reg->item?->title,
                'status'       => $reg->status,
                'submitted_at' => $reg->submitted_at?->toIso8601String(),
                'students'     => $reg->participants
                    ->filter(fn ($p) => ($p->student_id && $studentIds->contains($p->student_id)) || $p->teacher_id)
                    ->map(fn ($p) => $p->student?->only(['name', 'reg_no']) ?? $p->teacher?->only(['name', 'reg_no']))
                    ->values(),
            ]);

        return inertia('Portal/Group/FestRegistrations', [
            'school'        => $school->only('id', 'name'),
            'registrations' => $registrations,
        ]);
    }

    public function festSchedule(Request $request, string $tenantId)
    {
        $classIds = $this->assignedClassIds($request->user(), $tenantId);

        $studentIds = Student::where('tenant_id', $tenantId)
            ->when($classIds !== null, fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->active()
            ->pluck('id');

        $participants = FestParticipant::whereIn('student_id', $studentIds)
            ->whereHas('registration', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->whereHas('registration.event', fn ($q) => $q->whereIn('status', ['ongoing', 'registration_open', 'published']))
            ->with(['student:id,name,reg_no', 'registration.event:id,title', 'registration.item:id,title'])
            ->get();

        $rows = $participants->map(function (FestParticipant $p) {
            $schedule = FestSchedule::where('participant_id', $p->id)->first();

            return [
                'student_name' => $p->student?->name,
                'reg_no'       => $p->student?->reg_no,
                'event_title'  => $p->registration?->event?->title,
                'item_title'   => $p->registration?->item?->title,
                'chest_no'     => $p->chest_no,
                'level_reg'    => $p->level_registration_number,
                'scheduled_at' => $schedule?->scheduled_at?->toIso8601String(),
                'stage'        => $schedule?->stage,
                'sort_order'   => $schedule?->sort_order,
            ];
        })->sortBy(['scheduled_at', 'sort_order'])            ->values();

        return inertia('Portal/Group/FestSchedule', [
            'school' => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'rows'   => $rows,
        ]);
    }

    public function festClashes(Request $request, string $tenantId)
    {
        $classIds = $this->assignedClassIds($request->user(), $tenantId);
        $school = Tenant::findOrFail($tenantId);
        $sahodayaId = $school->parent_id;

        $studentIds = Student::where('tenant_id', $tenantId)
            ->when($classIds !== null, fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->active()
            ->pluck('id');

        $events = FestEvent::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->where('schedule_published', true)
            ->orderByDesc('event_start')
            ->get(['id', 'title']);

        $clashes = [];
        foreach ($events as $event) {
            $conflicts = (new \App\Services\Events\FestScheduleConflictService($event))
                ->detectAll($tenantId);

            foreach ($conflicts as $c) {
                if (! isset($c['student_id']) || ! $studentIds->contains($c['student_id'])) {
                    continue;
                }
                $clashes[] = array_merge($c, ['event_title' => $event->title, 'event_id' => $event->id]);
            }
        }

        return inertia('Portal/Group/FestClashes', [
            'school'  => $school->only('id', 'name'),
            'clashes' => $clashes,
        ]);
    }

    public function festAdmitCards(Request $request, string $tenantId)
    {
        $classIds = $this->assignedClassIds($request->user(), $tenantId);
        $school = Tenant::findOrFail($tenantId);

        $events = FestEvent::where('tenant_id', $school->parent_id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->whereHas('registrations', fn ($q) => $q
                ->where('school_id', $tenantId)
                ->where('status', 'approved'))
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type']);

        $typeToProgram = [
            'kalolsavam'   => 'kalotsav',
            'sports'       => 'sports-meet',
            'kids_fest'    => 'kids-fest',
            'teacher_fest' => 'teacher-fest',
        ];

        return inertia('Portal/Group/FestAdmitCards', [
            'school' => $school->only('id', 'name'),
            'events' => $events->map(fn ($e) => [
                'id'    => $e->id,
                'title' => $e->title,
                'program' => $typeToProgram[$e->event_type] ?? 'kalotsav',
                'download_url' => route('portal.group.fest.admit-cards.download', [
                    'tenantId' => $tenantId,
                    'event'    => $e->id,
                ]),
            ]),
        ]);
    }

    public function downloadAdmitCards(Request $request, string $tenantId, FestEvent $event)
    {
        $school = Tenant::findOrFail($tenantId);
        abort_if($event->tenant_id !== $school->parent_id, 403);

        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasRole('school_admin')) {
            $classIds = $this->assignedClassIds($user, $tenantId);
            abort_if($classIds === [], 403);
        }

        return (new FestReportService($event))->downloadAdmitCards(
            Request::create('/', 'GET', ['school_id' => $tenantId])
        );
    }

    /**
     * Returns class IDs this group_admin is allowed to see.
     * null = all classes (for school_admin / superadmin accessing this portal).
     *
     * @return list<int>|null
     */
    private function assignedClassIds($user, string $tenantId): ?array
    {
        if ($user->isSuperAdmin() || $user->hasRole('school_admin')) {
            return null;
        }

        // For group_admin: look up the class assignment stored in user meta or a dedicated table.
        // For now, we use a JSON column `group_classes` on the users table if it exists,
        // or fall back to all classes if not configured.
        $raw = $user->group_classes ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (is_array($raw) && count($raw) > 0) {
            return $raw;
        }

        // No assignment yet — return an empty list (show nothing) for security
        return [];
    }
}

<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\AdmissionEnquiry;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\NewsArticle;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\SchoolDocument;
use App\Models\Student;
use App\Models\DataChangeLog;
use App\Support\AcademicYear;
use App\Support\ProgramRouteMap;

class DashboardController extends SchoolAdminController
{
    public function index()
    {
        $user = request()->user();
        if ($user && app(\App\Services\School\SchoolUserScopeService::class)->isCoordinatorOnly($user)) {
            return redirect(app(\App\Services\School\SchoolUserScopeService::class)->homeUrlFor($user, $this->school->id));
        }

        $tid = $this->school->id;

        return $this->inertia('School/Dashboard', [
            'stats' => [
                ['label' => 'Active Students',      'value' => Student::where('tenant_id', $tid)->where('status', 'active')->count()],
                ['label' => 'Teachers',            'value' => \App\Models\Teacher::where('tenant_id', $tid)->count()],
                ['label' => 'New Enquiries',       'value' => AdmissionEnquiry::where('tenant_id', $tid)->where('status', 'new')->count()],
                ['label' => 'Unverified Students', 'value' => Student::where('tenant_id', $tid)->where('status', 'active')->whereNull('verified_at')->count()],
            ],
            'documentAlerts' => $this->documentAlerts($tid),
            'programSummaries' => $this->programSummaries(),
            'dashboardExtras'  => app(\App\Services\Events\ProgramHubDataService::class)->schoolDashboardExtras($this->school),
            'setup' => $this->setupStatus(),
            'membershipComplete' => $this->membershipComplete(),
            'recentActivity' => $this->recentActivity(),
            'registrationWindow' => app(\App\Services\Membership\MembershipRegistrationWindowService::class)
                ->displayPayload(
                    app(\App\Services\Membership\MembershipRegistrationWindowService::class)
                        ->forSchool($this->school, AcademicYear::forSchool($this->school))
                ),
            'showSetupWizard' => ! $this->school->school_setup_wizard_dismissed
                && $this->school->membership_status === 'approved',
        ]);
    }

    public function dismissSetupWizard()
    {
        $this->school->update(['school_setup_wizard_dismissed' => true]);

        return back();
    }

    /** @return list<array<string, mixed>> */
    private function recentActivity(): array
    {
        return DataChangeLog::query()
            ->where('school_id', $this->school->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'action', 'description', 'log_name', 'created_at'])
            ->map(fn (DataChangeLog $log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'log_name'    => $log->log_name,
                'created_at'  => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function programSummaries(): array
    {
        $sahodayaId = $this->school->parent_id;
        if (! $sahodayaId) {
            return [];
        }

        $programs = [
            ['slug' => 'kalotsav', 'label' => 'Kalotsav', 'type' => 'kalolsavam'],
            ['slug' => 'sports-meet', 'label' => 'Sports Meet', 'type' => 'sports'],
            ['slug' => 'kids-fest', 'label' => 'Kids Fest', 'type' => 'kids_fest'],
            ['slug' => 'teacher-fest', 'label' => 'Teacher Fest', 'type' => 'teacher_fest'],
            ['slug' => 'english-fest', 'label' => 'English Fest', 'type' => 'english_fest'],
            ['slug' => 'science-fest', 'label' => 'Science Fest', 'type' => 'science_fest'],
            ['slug' => 'custom', 'label' => 'Custom Events', 'type' => 'custom'],
        ];

        return collect($programs)->map(function (array $p) use ($sahodayaId) {
            $events = FestEvent::where('tenant_id', $sahodayaId)->ofType($p['type'])->visibleToSchool($this->school->id);
            $open = (clone $events)->whereIn('status', ['published', 'registration_open', 'ongoing'])->count();
            $eventIds = (clone $events)->pluck('id');
            $regs = FestRegistration::where('school_id', $this->school->id)->whereIn('event_id', $eventIds)->whereIn('status', ['submitted', 'approved'])->count();
            $feesPending = FestSchoolEventFee::where('school_id', $this->school->id)->whereIn('event_id', $eventIds)->whereIn('status', ['pending', 'proof_uploaded'])->count();

            return [
                'slug'           => $p['slug'],
                'label'          => $p['label'],
                'open_events'    => $open,
                'registrations'  => $regs,
                'fees_pending'   => $feesPending,
                'hub_url'        => '/school-admin/'.$this->school->id.'/'.ProgramRouteMap::prefixFromSlug($p['slug']),
            ];
        })->all();
    }

    private function setupStatus(): array
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $sahodaya = $this->school->parent;
        $profile = $sahodaya
            ? SahodayaProfile::where('tenant_id', $sahodaya->id)->first()
            : null;
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->first();

        $schoolCode = $this->school->school_prefix;
        $sahodayaPrefix = $profile?->prefix;
        $regNoExample = ($sahodayaPrefix && $schoolCode)
            ? strtoupper($sahodayaPrefix).'/'.strtoupper($schoolCode).'/'.AcademicYear::yearSuffix($academicYear).'/0001'
            : null;

        $classCount = $this->schoolClasses()->count();
        $studentCount = Student::where('tenant_id', $this->school->id)->where('status', 'active')->count();
        $requiresStudents = $profile?->student_data_mode === 'full_records';

        return [
            'academicYear'    => $academicYear,
            'hasSchoolCode'   => filled($schoolCode),
            'schoolCode'      => $schoolCode,
            'codeLocked'      => (bool) $this->school->prefixes_locked,
            'requiresStudents'=> $requiresStudents,
            'studentDataMode' => $profile?->student_data_mode,
            'suggestedCode'   => strtoupper(substr(preg_replace('/[^a-z]/i', '', $this->school->name), 0, 3)) ?: 'SCH',
            'regNoExample'    => $regNoExample,
            'hasClasses'      => $classCount > 0,
            'classCount'      => $classCount,
            'studentCount'    => $studentCount,
            'hasRegistration' => (bool) $registration,
            'registrationStatus' => $registration?->registration_status,
        ];
    }

    private function membershipComplete(): ?array
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->first();

        if ($registration?->registration_status !== 'completed') {
            return null;
        }

        return [
            'academicYear' => $academicYear,
            'regNo'        => $registration->reg_no,
        ];
    }

    /** @return array{expired: int, expiring_soon: int, rejected: int, pending: int} */
    private function documentAlerts(string $schoolId): array
    {
        $base = SchoolDocument::where('school_id', $schoolId);

        return [
            'expired'       => (clone $base)->where('status', 'expired')->count(),
            'expiring_soon' => (clone $base)->where('status', 'approved')
                ->whereNotNull('valid_to')
                ->whereBetween('valid_to', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count(),
            'rejected'      => (clone $base)->where('status', 'rejected')->count(),
            'pending'       => (clone $base)->where('status', 'pending')->count(),
        ];
    }
}

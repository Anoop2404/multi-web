<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class StudentVerificationController extends SahodayaAdminController
{
    public function index(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $filters = $request->validate([
            'school_id'         => 'nullable|string',
            'verification'      => 'nullable|in:all,verified,unverified',
            'class_category_id' => 'nullable|integer',
            'search'            => 'nullable|string|max:100',
        ]);

        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $base = Student::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('status', 'active');

        $counts = [
            'total'      => (clone $base)->count(),
            'verified'   => (clone $base)->whereNotNull('verified_at')->count(),
            'unverified' => (clone $base)->whereNull('verified_at')->count(),
        ];

        $schools = Tenant::whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name']);

        $statsBySchool = (clone $base)
            ->selectRaw('tenant_id, count(*) as total, sum(case when verified_at is null then 1 else 0 end) as unverified')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $schoolSummaries = $schools->map(function (Tenant $school) use ($statsBySchool) {
            $row = $statsBySchool->get($school->id);
            $total = (int) ($row->total ?? 0);
            $unverified = (int) ($row->unverified ?? 0);

            return [
                'id'         => $school->id,
                'name'       => $school->name,
                'total'      => $total,
                'verified'   => $total - $unverified,
                'unverified' => $unverified,
            ];
        });

        $verification = $filters['verification'] ?? 'all';

        if ($verification === 'unverified') {
            $schoolSummaries = $schoolSummaries->filter(fn (array $row) => $row['unverified'] > 0);
        } elseif ($verification === 'verified') {
            $schoolSummaries = $schoolSummaries->filter(fn (array $row) => $row['total'] > 0 && $row['unverified'] === 0);
        }

        if (! empty($filters['search']) && empty($filters['school_id'])) {
            $term = mb_strtolower($filters['search']);
            $schoolSummaries = $schoolSummaries->filter(
                fn (array $row) => str_contains(mb_strtolower($row['name']), $term)
            );
        }

        $schoolSummaries = $schoolSummaries->sortBy([
            ['unverified', 'desc'],
            ['name', 'asc'],
        ])->values();

        $selectedSchool = null;
        $students = null;

        if (! empty($filters['school_id'])) {
            $school = $schools->firstWhere('id', $filters['school_id']);
            if ($school) {
                $row = $statsBySchool->get($school->id);
                $total = (int) ($row->total ?? 0);
                $unverified = (int) ($row->unverified ?? 0);
                $selectedSchool = [
                    'id'         => $school->id,
                    'name'       => $school->name,
                    'total'      => $total,
                    'verified'   => $total - $unverified,
                    'unverified' => $unverified,
                ];
            }

            $students = (clone $base)
                ->with(['schoolClass.classCategory', 'verifiedBy:id,name,email'])
                ->where('tenant_id', $filters['school_id'])
                ->when($verification === 'verified', fn ($q) => $q->whereNotNull('verified_at'))
                ->when($verification === 'unverified', fn ($q) => $q->whereNull('verified_at'))
                ->when(! empty($filters['class_category_id']), fn ($q) => $q->whereHas(
                    'schoolClass',
                    fn ($c) => $c->where('class_category_id', $filters['class_category_id'])
                ))
                ->when(! empty($filters['search']), function ($q) use ($filters) {
                    $term = '%'.$filters['search'].'%';
                    $q->where(fn ($inner) => $inner
                        ->where('name', 'like', $term)
                        ->orWhere('reg_no', 'like', $term)
                        ->orWhere('admission_number', 'like', $term));
                })
                ->orderBy('name')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (Student $s) => $this->mapStudent($s));
        }

        return $this->inertia('Sahodaya/Students/Verification', [
            'students'        => $students,
            'schoolSummaries' => $schoolSummaries,
            'selectedSchool'  => $selectedSchool,
            'counts'          => $counts,
            'filters'         => array_merge([
                'school_id' => '',
                'verification' => 'all',
                'class_category_id' => null,
                'search' => '',
            ], $filters),
            'schools'    => $schools,
            'categories' => $resolver->classCategories($this->sahodaya->id),
        ]);
    }

    public function verify(Request $request, string $tenantId, Student $student)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($student->tenant?->parent_id !== $this->sahodaya->id, 403);

        if ($student->verified_at) {
            return back()->with('success', 'Student is already verified.');
        }

        $student->update([
            'verified_at'         => now(),
            'verified_by_user_id' => $request->user()?->id,
            'rejection_reason'    => null,
        ]);

        app(DataChangeLogger::class)->event(
            'verified',
            "Student verified by Sahodaya: {$student->name}",
            $student->tenant_id,
            'students',
            $student,
            ['student_id' => $student->id, 'verified_by' => 'sahodaya'],
        );

        $this->notifySchool($student->tenant_id, 'student.verification.approved', [
            'student_name' => $student->name,
        ]);

        return back()->with('success', "Verified {$student->name}.");
    }

    public function reject(Request $request, string $tenantId, Student $student)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($student->tenant?->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['reason' => 'required|string|max:500']);

        $student->update([
            'verified_at'         => null,
            'verified_by_user_id' => null,
            'rejection_reason'    => $data['reason'],
        ]);

        app(DataChangeLogger::class)->event(
            'rejected',
            "Student verification rejected: {$student->name}",
            $student->tenant_id,
            'students',
            $student,
            ['student_id' => $student->id, 'reason' => $data['reason']],
        );

        $this->notifySchool($student->tenant_id, 'student.verification.rejected', [
            'student_name' => $student->name,
            'reason'       => $data['reason'],
        ]);

        return back()->with('success', "Rejected {$student->name}.");
    }

    public function bulkVerify(Request $request)
    {
        $this->assertStaffCan('membership.manage');
        $data = $request->validate([
            'student_ids'           => 'nullable|array',
            'student_ids.*'         => 'integer',
            'verify_all_unverified' => 'boolean',
            'school_id'             => 'nullable|string',
        ]);

        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id');

        if (! empty($data['student_ids'])) {
            $query = Student::whereIn('tenant_id', $schoolIds)
                ->where('status', 'active')
                ->whereNull('verified_at')
                ->whereIn('id', $data['student_ids']);
        } elseif ($data['verify_all_unverified'] ?? false) {
            $query = Student::whereIn('tenant_id', $schoolIds)
                ->where('status', 'active')
                ->whereNull('verified_at')
                ->when(! empty($data['school_id']), fn ($q) => $q->where('tenant_id', $data['school_id']));
        } else {
            abort(422, 'Select students or choose verify all unverified.');
        }

        $affected = (clone $query)->get(['id', 'tenant_id', 'name']);

        $count = $query->update([
            'verified_at'         => now(),
            'verified_by_user_id' => $request->user()?->id,
            'rejection_reason'    => null,
        ]);

        foreach ($affected->groupBy('tenant_id') as $schoolId => $students) {
            $this->notifySchool((string) $schoolId, 'student.verification.approved', [
                'student_name' => $students->count() === 1
                    ? $students->first()->name
                    : "{$students->count()} students",
            ]);
        }

        return back()->with('success', $count > 0 ? "Verified {$count} student(s)." : 'No unverified students matched.');
    }

    public function bulkReject(Request $request)
    {
        $this->assertStaffCan('membership.manage');
        $data = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer',
            'reason'        => 'required|string|max:500',
        ]);

        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id');

        $query = Student::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->whereNull('verified_at')
            ->whereIn('id', $data['student_ids']);

        $affected = (clone $query)->get(['id', 'tenant_id', 'name']);

        $count = $query->update([
            'verified_at'         => null,
            'verified_by_user_id' => null,
            'rejection_reason'    => $data['reason'],
        ]);

        foreach ($affected->groupBy('tenant_id') as $schoolId => $students) {
            $this->notifySchool((string) $schoolId, 'student.verification.rejected', [
                'student_name' => $students->count() === 1
                    ? $students->first()->name
                    : "{$students->count()} students",
                'reason' => $data['reason'],
            ]);
        }

        return back()->with('success', $count > 0 ? "Rejected {$count} student(s)." : 'No pending students matched.');
    }

    /** Notify a school's admin/staff users from a NotificationTemplate slug. */
    private function notifySchool(string $schoolId, string $slug, array $replacements = []): void
    {
        $service = app(NotificationService::class);

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, $slug, $replacements, "/school-admin/{$schoolId}/students");
        }
    }

    /** @return array<string, mixed> */
    private function mapStudent(Student $student): array
    {
        $school = Tenant::find($student->tenant_id);

        return [
            'id'                => $student->id,
            'name'              => $student->name,
            'reg_no'            => $student->reg_no,
            'admission_number'  => $student->admission_number,
            'roll_number'       => $student->roll_number,
            'gender'            => $student->gender,
            'dob'               => $student->dob?->format('Y-m-d'),
            'dob_display'       => $student->dob?->format('j M Y'),
            'age_years'         => $student->dob ? (int) $student->dob->diffInYears(now()) : null,
            'class_name'        => $student->schoolClass?->name,
            'category'          => $student->schoolClass?->classCategory?->label,
            'parent_name'       => $student->parent_name,
            'parent_phone'      => $student->parent_phone,
            'parent_email'      => $student->parent_email,
            'photo_url'         => $student->photoUrl(),
            'school_id'         => $student->tenant_id,
            'school_name'       => $school?->name,
            'is_verified'       => $student->isVerified(),
            'verified_at'       => $student->verified_at?->toIso8601String(),
            'verified_at_display' => $student->verified_at?->format('j M Y'),
            'verified_by'       => $student->verifiedBy?->name ?? $student->verifiedBy?->email,
            'rejection_reason'  => $student->rejection_reason,
        ];
    }
}

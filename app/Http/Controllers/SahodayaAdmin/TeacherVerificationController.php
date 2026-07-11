<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TeacherVerificationController extends SahodayaAdminController
{
    /** @var Collection<int, string>|null */
    private ?Collection $subjectLabelMap = null;

    private function subjectLabelMap(): Collection
    {
        return $this->subjectLabelMap ??= Subject::forSahodaya($this->sahodaya->id)->pluck('label', 'id');
    }

    public function index(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $filters = $request->validate([
            'school_id'      => 'nullable|string',
            'verification'   => 'nullable|in:all,verified,unverified',
            'search'         => 'nullable|string|max:100',
            'teaching_type_id' => 'nullable|integer',
        ]);

        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $base = Teacher::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('status', 'active');

        $counts = [
            'total'      => (clone $base)->count(),
            'verified'   => (clone $base)->whereNotNull('verified_at')->count(),
            'unverified' => (clone $base)->whereNull('verified_at')->count(),
        ];

        $teachers = (clone $base)
            ->with(['teachingType', 'verifiedBy:id,name,email'])
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->when(! empty($filters['teaching_type_id']), fn ($q) => $q->where('teaching_type_id', $filters['teaching_type_id']))
            ->when(($filters['verification'] ?? 'all') === 'verified', fn ($q) => $q->whereNotNull('verified_at'))
            ->when(($filters['verification'] ?? 'all') === 'unverified', fn ($q) => $q->whereNull('verified_at'))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('reg_no', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('tenant_id')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $schools = Tenant::whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name']);

        return $this->inertia('Sahodaya/Teachers/Verification', [
            'teachers'   => $teachers->through(fn (Teacher $t) => $this->mapTeacher($t)),
            'counts'     => $counts,
            'filters'    => array_merge([
                'school_id' => '',
                'verification' => 'all',
                'search' => '',
                'teaching_type_id' => '',
            ], $filters),
            'schools'    => $schools,
            'teachingTypes' => $resolver->teachingTypes($this->sahodaya->id),
        ]);
    }

    public function verify(Request $request, string $tenantId, Teacher $teacher)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($teacher->tenant?->parent_id !== $this->sahodaya->id, 403);

        if ($teacher->verified_at) {
            return back()->with('success', 'Teacher is already verified.');
        }

        $teacher->update([
            'verified_at'         => now(),
            'verified_by_user_id' => $request->user()?->id,
            'rejection_reason'    => null,
        ]);

        app(DataChangeLogger::class)->event(
            'verified',
            "Teacher verified by Sahodaya: {$teacher->name}",
            $teacher->tenant_id,
            'teachers',
            $teacher,
            ['teacher_id' => $teacher->id, 'verified_by' => 'sahodaya'],
        );

        $this->notifySchool($teacher->tenant_id, 'teacher.verification.approved', [
            'teacher_name' => $teacher->name,
        ]);

        return back()->with('success', "Verified {$teacher->name}.");
    }

    public function reject(Request $request, string $tenantId, Teacher $teacher)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($teacher->tenant?->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['reason' => 'required|string|max:500']);

        $teacher->update([
            'verified_at'         => null,
            'verified_by_user_id' => null,
            'rejection_reason'    => $data['reason'],
        ]);

        app(DataChangeLogger::class)->event(
            'rejected',
            "Teacher verification rejected: {$teacher->name}",
            $teacher->tenant_id,
            'teachers',
            $teacher,
            ['teacher_id' => $teacher->id, 'reason' => $data['reason']],
        );

        $this->notifySchool($teacher->tenant_id, 'teacher.verification.rejected', [
            'teacher_name' => $teacher->name,
            'reason'       => $data['reason'],
        ]);

        return back()->with('success', "Rejected {$teacher->name}.");
    }

    public function bulkVerify(Request $request)
    {
        $this->assertStaffCan('membership.manage');
        $data = $request->validate([
            'teacher_ids'           => 'nullable|array',
            'teacher_ids.*'         => 'integer',
            'verify_all_unverified' => 'boolean',
            'school_id'             => 'nullable|string',
        ]);

        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id');

        if (! empty($data['teacher_ids'])) {
            $query = Teacher::whereIn('tenant_id', $schoolIds)
                ->where('status', 'active')
                ->whereNull('verified_at')
                ->whereIn('id', $data['teacher_ids']);
        } elseif ($data['verify_all_unverified'] ?? false) {
            $query = Teacher::whereIn('tenant_id', $schoolIds)
                ->where('status', 'active')
                ->whereNull('verified_at')
                ->when(! empty($data['school_id']), fn ($q) => $q->where('tenant_id', $data['school_id']));
        } else {
            abort(422, 'Select teachers or choose verify all unverified.');
        }

        $affected = (clone $query)->get(['id', 'tenant_id', 'name']);

        $count = $query->update([
            'verified_at'         => now(),
            'verified_by_user_id' => $request->user()?->id,
            'rejection_reason'    => null,
        ]);

        foreach ($affected->groupBy('tenant_id') as $schoolId => $teachers) {
            $this->notifySchool((string) $schoolId, 'teacher.verification.approved', [
                'teacher_name' => $teachers->count() === 1
                    ? $teachers->first()->name
                    : "{$teachers->count()} teachers",
            ]);
        }

        return back()->with('success', $count > 0 ? "Verified {$count} teacher(s)." : 'No unverified teachers matched.');
    }

    /** Notify a school's admin/staff users from a NotificationTemplate slug. */
    private function notifySchool(string $schoolId, string $slug, array $replacements = []): void
    {
        $service = app(NotificationService::class);

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, $slug, $replacements, "/school-admin/{$schoolId}/teachers");
        }
    }

    /** @return array<string, mixed> */
    private function mapTeacher(Teacher $teacher): array
    {
        $school = Tenant::find($teacher->tenant_id);

        return [
            'id'              => $teacher->id,
            'name'            => $teacher->name,
            'reg_no'          => $teacher->reg_no,
            'email'           => $teacher->email,
            'mobile'          => $teacher->mobile,
            'category'        => $teacher->teachingType?->label,
            'subjects'        => collect($teacher->subject_ids ?? [])
                ->map(fn ($id) => $this->subjectLabelMap()->get($id))
                ->filter()
                ->values()
                ->all(),
            'school_id'       => $teacher->tenant_id,
            'school_name'     => $school?->name,
            'is_verified'     => $teacher->isVerified(),
            'verified_at'     => $teacher->verified_at?->toIso8601String(),
            'verified_by'     => $teacher->verifiedBy?->name ?? $teacher->verifiedBy?->email,
            'rejection_reason'=> $teacher->rejection_reason,
        ];
    }
}

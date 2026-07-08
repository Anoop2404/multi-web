<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;
use Illuminate\Http\Request;

class SchoolStudentsController extends SahodayaAdminController
{
    public function show(Request $request, string $tenantId, Tenant $school, EffectiveMasterDataResolver $resolver)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $filters = $request->validate([
            'class_category_id' => 'nullable|integer',
            'school_class_id'   => 'nullable|integer',
            'search'            => 'nullable|string|max:100',
            'verification'      => 'nullable|in:all,verified,unverified',
        ]);

        $categories = $resolver->classCategories($this->sahodaya->id);
        $classes = SchoolClass::where('tenant_id', $school->id)
            ->with('classCategory')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $categoryCounts = Student::query()
            ->where('students.tenant_id', $school->id)
            ->where('students.status', 'active')
            ->whereHas('schoolClass')
            ->join('school_classes', 'students.school_class_id', '=', 'school_classes.id')
            ->selectRaw('school_classes.class_category_id, count(*) as total')
            ->groupBy('school_classes.class_category_id')
            ->pluck('total', 'class_category_id');

        $students = Student::where('tenant_id', $school->id)
            ->with(['schoolClass.classCategory'])
            ->when(! empty($filters['class_category_id']), function ($q) use ($filters) {
                $q->whereHas('schoolClass', fn ($c) => $c->where('class_category_id', $filters['class_category_id']));
            })
            ->when(! empty($filters['school_class_id']), fn ($q) => $q->where('school_class_id', $filters['school_class_id']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('admission_number', 'like', $term)
                        ->orWhere('roll_number', 'like', $term)
                        ->orWhere('reg_no', 'like', $term);
                });
            })
            ->when(($filters['verification'] ?? 'all') === 'verified', fn ($q) => $q->whereNotNull('verified_at'))
            ->when(($filters['verification'] ?? 'all') === 'unverified', fn ($q) => $q->whereNull('verified_at'))
            ->orderBy('school_class_id')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $verifiedCount = Student::where('tenant_id', $school->id)->where('status', 'active')->whereNotNull('verified_at')->count();
        $unverifiedCount = Student::where('tenant_id', $school->id)->where('status', 'active')->whereNull('verified_at')->count();

        return $this->inertia('Sahodaya/Schools/Students', [
            'school'         => $school->only('id', 'name', 'school_prefix', 'membership_status'),
            'categories'     => $categories->map(fn ($cat) => [
                'id'            => $cat->id,
                'code'          => $cat->code,
                'label'         => $cat->label,
                'min_class'     => $cat->min_class,
                'max_class'     => $cat->max_class,
                'student_count' => (int) ($categoryCounts[$cat->id] ?? 0),
            ])->values(),
            'classes'        => $classes,
            'students'       => $students,
            'filters'        => array_merge(['verification' => 'all'], $filters),
            'totalStudents'  => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
            'verifiedCount'  => $verifiedCount,
            'unverifiedCount'=> $unverifiedCount,
            'classesCount'   => $classes->where('is_active', true)->count(),
        ]);
    }
}

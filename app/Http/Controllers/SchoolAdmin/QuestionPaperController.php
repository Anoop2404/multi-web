<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\QuestionPaper;
use App\Models\Teacher;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\AcademicYear;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class QuestionPaperController extends SchoolAdminController
{
    public function index(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $this->assertLeadershipAccess($request);

        $filters = $request->validate([
            'school_class_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'teacher_id' => 'nullable|integer',
            'academic_year' => 'nullable|string|max:20',
            'search' => 'nullable|string|max:100',
        ]);

        $papers = QuestionPaper::query()
            ->where('school_id', $this->school->id)
            ->with(['teacher:id,name', 'schoolClass:id,name', 'files'])
            ->when($filters['school_class_id'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($filters['academic_year'] ?? null, fn ($q, $year) => $q->where('academic_year', $year))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $term = '%'.$search.'%';
                $q->where(fn ($inner) => $inner
                    ->where('title', 'like', $term)
                    ->orWhere('exam_name', 'like', $term)
                    ->orWhere('original_name', 'like', $term));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return $this->inertia('School/QuestionPapers/Index', [
            'papers' => $papers,
            'filters' => $filters,
            'classes' => $this->schoolClasses()->map(fn ($class) => $class->only('id', 'name'))->values(),
            'subjects' => $resolver->subjects($this->school->parent_id)->map(fn ($subject) => $subject->only('id', 'label'))->values(),
            'teachers' => Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get(['id', 'name']),
            'academicYears' => QuestionPaper::where('school_id', $this->school->id)
                ->distinct()->pluck('academic_year')
                ->merge(AcademicYear::options())
                ->filter()->unique()->sortDesc()->values(),
        ]);
    }

    public function download(Request $request, string $tenantId, int $paper, int $file)
    {
        $this->assertLeadershipAccess($request);
        $questionPaper = QuestionPaper::where('school_id', $this->school->id)->findOrFail($paper);
        $questionPaperFile = $questionPaper->files()->findOrFail($file);

        return TenantStorage::downloadPrivate($questionPaperFile->file_path, $questionPaperFile->storage_disk, $questionPaperFile->original_name);
    }

    public function preview(Request $request, string $tenantId, int $paper, int $file)
    {
        $this->assertLeadershipAccess($request);
        $questionPaper = QuestionPaper::where('school_id', $this->school->id)->findOrFail($paper);
        $questionPaperFile = $questionPaper->files()->findOrFail($file);

        return TenantStorage::downloadPrivate($questionPaperFile->file_path, $questionPaperFile->storage_disk, $questionPaperFile->original_name, inline: true);
    }

    private function assertLeadershipAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdmin() || $user?->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']), 403, 'You don\'t have permission to manage question papers.');
    }
}

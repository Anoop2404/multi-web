<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\QuestionPaper;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\AcademicYear;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TeacherQuestionPaperController extends Controller
{
    public function index(Request $request, string $tenantId, EffectiveMasterDataResolver $resolver)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);
        [$classes, $subjects] = $this->availableChoices($teacher, $school, $resolver);

        $papers = QuestionPaper::query()
            ->where('school_id', $school->id)
            ->where('teacher_id', $teacher->id)
            ->with('schoolClass:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return inertia('Portal/Teacher/QuestionPapers', [
            'school' => $school->only('id', 'name'),
            'teacher' => $teacher->only('id', 'name'),
            'papers' => $papers,
            'classes' => $classes->values(),
            'subjects' => $subjects->values(),
            'academicYears' => $this->academicYears($school),
            'currentAcademicYear' => AcademicYear::forSchool($school),
        ]);
    }

    public function store(Request $request, string $tenantId, EffectiveMasterDataResolver $resolver)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);
        $data = $this->validated($request, true);
        [$class, $subject] = $this->resolveAuthorizedChoices($teacher, $school, $resolver, $data);
        $file = $request->file('file');
        $disk = TenantStorage::uploadDisk();
        $path = TenantStorage::storeUploadedFile($file, "schools/{$school->id}/question-papers/{$teacher->id}", $disk);

        QuestionPaper::create([
            ...$this->paperAttributes($data, $file),
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'class_name' => $class->name,
            'subject_id' => $subject->id,
            'subject_name' => $subject->label,
            'file_path' => $path,
            'storage_disk' => $disk,
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Question paper uploaded.');
    }

    public function update(Request $request, string $tenantId, int $paper, EffectiveMasterDataResolver $resolver)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);
        $questionPaper = $this->ownedPaper($school->id, $teacher->id, $paper);
        $data = $this->validated($request, false);
        [$class, $subject] = $this->resolveAuthorizedChoices($teacher, $school, $resolver, $data);

        $attributes = [
            'title' => $data['title'],
            'school_class_id' => $class->id,
            'class_name' => $class->name,
            'subject_id' => $subject->id,
            'subject_name' => $subject->label,
            'academic_year' => $data['academic_year'],
            'exam_name' => $data['exam_name'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $disk = TenantStorage::uploadDisk();
            $path = TenantStorage::storeUploadedFile($file, "schools/{$school->id}/question-papers/{$teacher->id}", $disk);
            $oldPath = $questionPaper->file_path;
            $oldDisk = $questionPaper->storage_disk;
            $attributes = [...$attributes, ...$this->fileAttributes($file), 'file_path' => $path, 'storage_disk' => $disk];
            $questionPaper->update($attributes);
            $this->deleteStoredFile($oldPath, $oldDisk);
        } else {
            $questionPaper->update($attributes);
        }

        return back()->with('success', 'Question paper updated.');
    }

    public function download(Request $request, string $tenantId, int $paper)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $questionPaper = $this->ownedPaper($tenantId, $teacher->id, $paper);

        return TenantStorage::downloadPrivate($questionPaper->file_path, $questionPaper->storage_disk, $questionPaper->original_name);
    }

    public function destroy(Request $request, string $tenantId, int $paper)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $questionPaper = $this->ownedPaper($tenantId, $teacher->id, $paper);
        $questionPaper->delete();

        return back()->with('success', 'Question paper removed.');
    }

    private function ownedPaper(string $schoolId, int $teacherId, int $paper): QuestionPaper
    {
        return QuestionPaper::query()
            ->where('school_id', $schoolId)
            ->where('teacher_id', $teacherId)
            ->findOrFail($paper);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $fileRequired): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'school_class_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{2,4}$/'],
            'exam_name' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'file' => ($fileRequired ? 'required' : 'nullable').'|file|mimes:pdf,doc,docx,odt,rtf,jpg,jpeg,png|max:20480',
        ]);
    }

    /** @return array{0: Collection, 1: Collection} */
    private function availableChoices(Teacher $teacher, Tenant $school, EffectiveMasterDataResolver $resolver): array
    {
        $assignedClasses = $teacher->schoolClasses()->where('tenant_id', $school->id)->active()->get(['school_classes.id', 'school_classes.name']);
        $classes = $assignedClasses->isNotEmpty()
            ? $assignedClasses
            : SchoolClass::where('tenant_id', $school->id)->active()->orderBy('display_order')->orderBy('name')->get(['id', 'name']);

        $subjects = $resolver->subjects($school->parent_id);
        $subjectIds = array_map('intval', $teacher->subject_ids ?? []);
        $subjects = $subjectIds === [] ? collect() : $subjects->whereIn('id', $subjectIds);

        return [$classes, $subjects];
    }

    /** @return array{0: SchoolClass, 1: Subject} */
    private function resolveAuthorizedChoices(Teacher $teacher, Tenant $school, EffectiveMasterDataResolver $resolver, array $data): array
    {
        [$classes, $subjects] = $this->availableChoices($teacher, $school, $resolver);
        $class = $classes->firstWhere('id', (int) $data['school_class_id']);
        $subject = $subjects->firstWhere('id', (int) $data['subject_id']);

        if (! $class || ! $subject) {
            throw ValidationException::withMessages([
                $class ? 'subject_id' : 'school_class_id' => $class
                    ? 'Select one of your assigned subjects.'
                    : 'Select one of your available classes.',
            ]);
        }

        return [$class, $subject];
    }

    /** @return array<string, mixed> */
    private function paperAttributes(array $data, UploadedFile $file): array
    {
        return [
            'title' => $data['title'],
            'academic_year' => $data['academic_year'],
            'exam_name' => $data['exam_name'] ?? null,
            'description' => $data['description'] ?? null,
            ...$this->fileAttributes($file),
        ];
    }

    /** @return array<string, mixed> */
    private function fileAttributes(UploadedFile $file): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    private function deleteStoredFile(?string $path, ?string $disk): void
    {
        if (! $path) {
            return;
        }

        try {
            Storage::disk(TenantStorage::resolveDisk($disk))->delete($path);
        } catch (\Throwable) {
            // The database update is authoritative; failed cleanup must not lose the new upload.
        }
    }

    /** @return list<string> */
    private function academicYears(Tenant $school): array
    {
        return array_values(array_unique([
            AcademicYear::forSchool($school),
            ...AcademicYear::options(),
        ]));
    }
}

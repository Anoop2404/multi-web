<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperFile;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\AcademicYear;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
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
            ->with(['schoolClass:id,name', 'files'])
            ->latest()
            ->get();

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
        $data = $this->validated($request);
        [$class, $subject] = $this->resolveAuthorizedChoices($teacher, $school, $resolver, $data);

        $paper = QuestionPaper::create([
            'title' => $data['title'],
            'academic_year' => $data['academic_year'],
            'exam_name' => $data['exam_name'] ?? null,
            'description' => $data['description'] ?? null,
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'class_name' => $class->name,
            'subject_id' => $subject->id,
            'subject_name' => $subject->label,
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $this->storeFilesFor($paper, $request->file('files'), $school, $teacher);

        return back()->with('success', 'Question paper uploaded.');
    }

    public function update(Request $request, string $tenantId, int $paper, EffectiveMasterDataResolver $resolver)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);
        $questionPaper = $this->ownedPaper($school->id, $teacher->id, $paper);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'school_class_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{2,4}$/'],
            'exam_name' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
        ]);
        [$class, $subject] = $this->resolveAuthorizedChoices($teacher, $school, $resolver, $data);

        $questionPaper->update([
            'title' => $data['title'],
            'school_class_id' => $class->id,
            'class_name' => $class->name,
            'subject_id' => $subject->id,
            'subject_name' => $subject->label,
            'academic_year' => $data['academic_year'],
            'exam_name' => $data['exam_name'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Question paper updated.');
    }

    public function storeFiles(Request $request, string $tenantId, int $paper)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);
        $questionPaper = $this->ownedPaper($school->id, $teacher->id, $paper);

        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:pdf,doc,docx,odt,rtf,jpg,jpeg,png|max:20480',
        ]);

        $this->storeFilesFor($questionPaper, $request->file('files'), $school, $teacher);

        return back()->with('success', 'Files added.');
    }

    public function downloadFile(Request $request, string $tenantId, int $paper, int $file)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $questionPaper = $this->ownedPaper($tenantId, $teacher->id, $paper);
        $questionPaperFile = $questionPaper->files()->findOrFail($file);

        return TenantStorage::downloadPrivate($questionPaperFile->file_path, $questionPaperFile->storage_disk, $questionPaperFile->original_name);
    }

    public function previewFile(Request $request, string $tenantId, int $paper, int $file)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $questionPaper = $this->ownedPaper($tenantId, $teacher->id, $paper);
        $questionPaperFile = $questionPaper->files()->findOrFail($file);

        return TenantStorage::downloadPrivate($questionPaperFile->file_path, $questionPaperFile->storage_disk, $questionPaperFile->original_name, inline: true);
    }

    public function destroyFile(Request $request, string $tenantId, int $paper, int $file)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $questionPaper = $this->ownedPaper($tenantId, $teacher->id, $paper);
        $questionPaperFile = $questionPaper->files()->findOrFail($file);

        if ($questionPaper->files()->count() <= 1) {
            throw ValidationException::withMessages([
                'file' => 'This is the only file on this entry — remove the whole entry instead.',
            ]);
        }

        $this->deleteStoredFile($questionPaperFile->file_path, $questionPaperFile->storage_disk);
        $questionPaperFile->delete();

        return back()->with('success', 'File removed.');
    }

    public function destroy(Request $request, string $tenantId, int $paper)
    {
        /** @var Teacher $teacher */
        $teacher = $request->attributes->get('portalTeacher');
        $questionPaper = $this->ownedPaper($tenantId, $teacher->id, $paper);

        foreach ($questionPaper->files as $file) {
            $this->deleteStoredFile($file->file_path, $file->storage_disk);
        }
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

    /** @param list<\Illuminate\Http\UploadedFile> $files */
    private function storeFilesFor(QuestionPaper $paper, array $files, Tenant $school, Teacher $teacher): void
    {
        $order = (int) ($paper->files()->max('display_order') ?? 0) + 1;
        $disk = TenantStorage::uploadDisk();

        foreach ($files as $file) {
            $path = TenantStorage::storeUploadedFile($file, "schools/{$school->id}/question-papers/{$teacher->id}", $disk);

            QuestionPaperFile::create([
                'question_paper_id' => $paper->id,
                'file_path' => $path,
                'storage_disk' => $disk,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'display_order' => $order++,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'school_class_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{2,4}$/'],
            'exam_name' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:pdf,doc,docx,odt,rtf,jpg,jpeg,png|max:20480',
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
                    ? 'Select a valid subject.'
                    : 'Select one of your available classes.',
            ]);
        }

        return [$class, $subject];
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

<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Http\Resources\StudentResource;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Students\StudentCsvImporter;
use App\Services\Students\StudentRegistrationNumberGenerator;
use App\Services\Students\StudentEditLockService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentApiController extends SchoolApiController
{
    public function index(Request $request)
    {
        abort_unless(filled($this->school->school_prefix), 403, 'Set school code first.');

        $filters = $request->validate([
            'class_category_id' => 'nullable|integer',
            'school_class_id'   => 'nullable|integer',
            'status'            => 'nullable|in:active,transferred,graduated,withdrawn,all',
            'search'            => 'nullable|string|max:100',
            'per_page'          => 'nullable|integer|min:1|max:100',
        ]);

        $query = Student::where('tenant_id', $this->school->id)
            ->with(['schoolClass.classCategory'])
            ->when(! empty($filters['class_category_id']), function ($q) use ($filters) {
                $q->whereHas('schoolClass', fn ($c) => $c->where('class_category_id', $filters['class_category_id']));
            })
            ->when(! empty($filters['school_class_id']), fn ($q) => $q->where('school_class_id', $filters['school_class_id']))
            ->when(($filters['status'] ?? 'active') !== 'all', fn ($q) => $q->where('status', $filters['status'] ?? 'active'))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('parent_email', 'like', $term)
                    ->orWhere('admission_number', 'like', $term));
            })
            ->orderBy('name');

        $students = $query->paginate($filters['per_page'] ?? 25);

        return StudentResource::collection($students)->additional([
            'meta' => [
                'categories' => app(EffectiveMasterDataResolver::class)
                    ->classCategories($this->school->parent_id)
                    ->values(),
                'classes' => SchoolClass::where('tenant_id', $this->school->id)
                    ->active()
                    ->with('classCategory')
                    ->orderBy('display_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'class_category_id']),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(filled($this->school->school_prefix), 403, 'Set school code first.');
        app(StudentEditLockService::class)->assertEditable($this->school);

        $data = $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'name'   => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'dob'    => 'nullable|date',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['status'] = 'active';
        $data['admission_number'] = app(StudentRegistrationNumberGenerator::class)->generate($this->school);

        $student = Student::create($data);

        app(DataChangeLogger::class)->created(
            $student,
            "Student registered: {$student->name}",
            $this->school->id,
            'students',
        );

        return $this->ok(StudentResource::make($student->load('schoolClass.classCategory')), 201);
    }

    public function update(Request $request, string $tenantId, string $studentId)
    {
        $student = Student::where('tenant_id', $this->school->id)->findOrFail($studentId);
        app(StudentEditLockService::class)->assertEditable($this->school);

        $data = $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'name'         => 'required|string|max:255',
            'gender'       => 'required|in:male,female,other',
            'dob'          => 'nullable|date',
            'parent_email' => 'nullable|email|max:255',
        ]);

        $student->update($data);

        return $this->ok(StudentResource::make($student->fresh()->load('schoolClass.classCategory')));
    }

    public function destroy(string $tenantId, string $studentId)
    {
        app(StudentEditLockService::class)->assertEditable($this->school);
        $student = Student::where('tenant_id', $this->school->id)->findOrFail($studentId);
        $student->delete();

        return $this->message('Student removed.');
    }

    public function uploadPhoto(Request $request, string $tenantId, string $studentId)
    {
        app(StudentEditLockService::class)->assertEditable($this->school);

        $student = Student::where('tenant_id', $this->school->id)->findOrFail($studentId);

        $request->validate(['photo' => 'required|image|max:2048']);

        $file = $request->file('photo');
        app(UploadBackupService::class)->store(
            $file,
            'student_photo',
            $this->school->id,
            $student,
            $request->user()->id,
            ['student_id' => $student->id, 'previous_photo' => $student->photo],
        );

        $student->update([
            'photo' => TenantStorage::storeUploadedFile($file, 'students/'.$this->school->id),
        ]);

        return $this->ok(StudentResource::make($student->fresh()->load('schoolClass.classCategory')));
    }

    public function showPhoto(string $tenantId, string $studentId)
    {
        $student = Student::where('tenant_id', $this->school->id)->findOrFail($studentId);
        abort_unless($student->photo, 404);

        return TenantStorage::downloadResponse($this->school, $student->photo);
    }

    public function importTemplate(): StreamedResponse
    {
        $csv = (new StudentCsvImporter($this->school))->templateCsvForSchool();

        return response()->streamDownload(
            fn () => print("\xEF\xBB\xBF".$csv),
            'student-import-sample.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function import(Request $request)
    {
        app(StudentEditLockService::class)->assertEditable($this->school);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file = $request->file('file');
        app(UploadBackupService::class)->store(
            $file,
            'student_import',
            $this->school->id,
            null,
            $request->user()->id,
        );

        $result = (new StudentCsvImporter($this->school))->import($file);

        return $this->ok($result);
    }
}

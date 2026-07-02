<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Portal\StudentPortalProvisioner;
use App\Services\Students\StudentEditChangeService;
use App\Services\Students\StudentEditLockService;
use App\Services\Students\StudentCsvImporter;
use App\Services\Students\StudentRecordCreator;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends SchoolAdminController
{
    public function index(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        $filters = $request->validate([
            'class_category_id' => 'nullable|integer',
            'school_class_id'   => 'nullable|integer',
            'status'            => 'nullable|in:active,transferred,graduated,withdrawn,all',
            'search'            => 'nullable|string|max:100',
            'sort'              => 'nullable|in:name,parent_email,status,class',
            'dir'               => 'nullable|in:asc,desc',
        ]);

        $sort = $filters['sort'] ?? 'name';
        $dir  = $filters['dir'] ?? 'asc';

        $query = Student::where('tenant_id', $this->school->id)
            ->with(['schoolClass.classCategory'])
            ->when(! empty($filters['class_category_id']), function ($q) use ($filters) {
                $q->whereHas('schoolClass', fn ($c) => $c->where('class_category_id', $filters['class_category_id']));
            })
            ->when(! empty($filters['school_class_id']), fn ($q) => $q->where('school_class_id', $filters['school_class_id']))
            ->when(($filters['status'] ?? 'active') !== 'all', function ($q) use ($filters) {
                $q->where('status', $filters['status'] ?? 'active');
            })
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('parent_email', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('admission_number', 'like', $term)
                        ->orWhere('reg_no', 'like', $term)
                        ->orWhere('roll_number', 'like', $term)
                        ->orWhere('parent_name', 'like', $term);
                });
            });

        if ($sort === 'class') {
            $query->leftJoin('school_classes', 'students.school_class_id', '=', 'school_classes.id')
                ->orderBy('school_classes.name', $dir)
                ->select('students.*');
        } else {
            $query->orderBy(match ($sort) {
                'parent_email' => 'parent_email',
                'status'       => 'status',
                default        => 'name',
            }, $dir);
        }

        return $this->inertia('School/Students/Index', [
            'students'   => $query->paginate(25)->withQueryString()->through(fn (Student $s) => $this->studentPayload($s)),
            'filters'    => array_merge([
                'status' => 'active',
                'sort'   => 'name',
                'dir'    => 'asc',
            ], $filters),
            'categories' => $this->classCategories()->values(),
            'classes'    => $this->schoolClasses(),
            'classNames' => SchoolClass::where('tenant_id', $this->school->id)->active()->orderBy('display_order')->orderBy('name')->pluck('name')->values(),
            'studentEditLock' => app(StudentEditLockService::class)->metaForSchool($this->school),
            'pendingChangeRequests' => \App\Models\StudentEditChangeRequest::where('school_id', $this->school->id)
                ->where('status', 'pending')
                ->count(),
        ]);
    }

    public function create()
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        app(StudentEditLockService::class)->assertCanAdd($this->school);

        return $this->inertia('School/Students/Create', [
            'categories' => $this->classCategories()->values(),
            'classes'    => $this->schoolClasses(),
        ]);
    }

    public function createBulk()
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        app(StudentEditLockService::class)->assertCanAdd($this->school);

        return $this->inertia('School/Students/BulkCreate', [
            'categories' => $this->classCategories()->values(),
            'classes'    => $this->schoolClasses(),
        ]);
    }

    public function store(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        app(StudentEditLockService::class)->assertCanAdd($this->school);

        $data = $this->validatedStudentCreate($request);

        $student = $this->createStudentRecord($data, $request->file('photo'));

        if ($request->boolean('create_login') && $request->filled('email')) {
            $result = app(StudentPortalProvisioner::class)->provision(
                $student,
                $request->input('email'),
                $request->input('password'),
            );

            return back()->with([
                'success'        => 'Student registered successfully.',
                'newCredentials' => [
                    'username' => $result['user']->username,
                    'password' => $result['password'],
                ],
            ]);
        }

        return back()->with('success', 'Student registered successfully.');
    }

    public function storeBulk(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        app(StudentEditLockService::class)->assertCanAdd($this->school);

        $data = $request->validate([
            'students' => 'required|array|min:1|max:25',
            'students.*.school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'students.*.name'   => 'required|string|max:255',
            'students.*.gender' => 'required|in:male,female,other',
            'students.*.dob'    => 'required|date|before:today',
            'students.*.photo'  => 'required|image|max:2048',
        ]);

        $created = 0;
        foreach ($data['students'] as $index => $row) {
            $photo = $request->file("students.{$index}.photo");
            $this->createStudentRecord($row, $photo);
            $created++;
        }

        return back()->with('success', "{$created} student(s) added.");
    }

    public function provisionPortal(Request $request, string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        $result = app(StudentPortalProvisioner::class)->provision($student, $data['email'], $data['password'] ?? null);

        return back()->with([
            'success'        => 'Student portal login created.',
            'newCredentials' => [
                'username' => $result['user']->username,
                'password' => $result['password'],
            ],
        ]);
    }

    public function edit(string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);

        return redirect("/school-admin/{$this->school->id}/students?edit={$student->id}");
    }

    public function updatePhoto(Request $request, string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        app(StudentEditLockService::class)->assertCanEdit($this->school);

        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        $file = $request->file('photo');
        $backup = app(UploadBackupService::class)->store(
            $file,
            'student_photo',
            $this->school->id,
            $student,
            $request->user()->id,
            ['student_id' => $student->id, 'previous_photo' => $student->photo],
        );

        $student->update([
            'photo' => TenantStorage::storeStudentPhoto($file, $this->school->id),
        ]);

        app(DataChangeLogger::class)->updated(
            $student,
            "Student photo updated: {$student->name}",
            ['photo' => ['old' => $backup->metadata['previous_photo'] ?? null, 'new' => $student->photo]],
            $this->school->id,
            'students',
            ['backup_id' => $backup->id],
        );

        return back()->with('success', 'Student photo updated.');
    }

    public function uploadPhotosZip(Request $request, string $tenantId)
    {
        app(StudentEditLockService::class)->assertCanEdit($this->school);

        $request->validate([
            'zip' => 'required|file|mimes:zip|max:51200',
        ]);

        $zipPath = $request->file('zip')->getRealPath();
        $zip = new \ZipArchive;
        abort_unless($zip->open($zipPath) === true, 422, 'Could not open zip file.');

        $updated = 0;
        $skipped = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! $entry || str_ends_with($entry, '/')) {
                continue;
            }

            $basename = pathinfo($entry, PATHINFO_FILENAME);
            if ($basename === '') {
                $skipped++;

                continue;
            }

            $student = Student::where('tenant_id', $this->school->id)
                ->where(function ($q) use ($basename) {
                    $q->where('reg_no', $basename)
                        ->orWhere('admission_number', $basename);
                })
                ->first();

            if (! $student) {
                $skipped++;

                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $skipped++;

                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION) ?: 'jpg');
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $skipped++;

                continue;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'fest-photo-');
            file_put_contents($tmp, $contents);

            $uploaded = new \Illuminate\Http\UploadedFile(
                $tmp,
                $basename.'.'.$ext,
                mime_content_type($tmp) ?: 'image/jpeg',
                null,
                true
            );

            $student->update([
                'photo' => TenantStorage::storeStudentPhoto($uploaded, $this->school->id),
            ]);

            @unlink($tmp);
            $updated++;
        }

        $zip->close();

        return back()->with('success', "Updated {$updated} student photo(s). {$skipped} file(s) skipped.");
    }

    public function update(Request $request, string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        app(StudentEditLockService::class)->assertCanEdit($this->school);

        $data = $this->validatedStudentBasicUpdate($request);
        $before = $student->only(array_keys($data));

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            app(UploadBackupService::class)->store(
                $file,
                'student_photo',
                $this->school->id,
                $student,
                $request->user()->id,
                ['student_id' => $student->id, 'previous_photo' => $student->photo],
            );
            $data['photo'] = TenantStorage::storeStudentPhoto($file, $this->school->id);
            $before['photo'] = $student->photo;
        }

        $student->update($data);

        app(DataChangeLogger::class)->updated(
            $student,
            "Student updated: {$student->name}",
            DataChangeLogger::diff($before, $student->only(array_keys($data))),
            $this->school->id,
            'students',
        );

        return back()->with('success', 'Student updated.');
    }

    public function destroy(string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        app(StudentEditLockService::class)->assertCanEdit($this->school);

        $snapshot = $student->only(['id', 'name', 'school_class_id', 'status', 'parent_email']);
        $name = $student->name;
        $student->delete();

        app(DataChangeLogger::class)->deleted(
            $student,
            "Student removed: {$name}",
            $this->school->id,
            'students',
            $snapshot,
        );

        return back()->with('success', 'Student record removed.');
    }

    public function submitCreateChangeRequest(Request $request, StudentEditChangeService $changeService)
    {
        $role = StudentEditChangeService::submittedByRole($request->user());
        $changeService->submitCreate($request, $this->school, $request->user()?->id, $role);

        return back()->with('success', 'New student request submitted for school review.');
    }

    public function submitChangeRequest(Request $request, string $tenantId, Student $student, StudentEditChangeService $changeService)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);

        $role = StudentEditChangeService::submittedByRole($request->user());
        $changeService->submitUpdate($request, $this->school, $student, $request->user()?->id, $role);

        return back()->with('success', 'Change request submitted for school review.');
    }

    public function changeRequests()
    {
        $requests = \App\Models\StudentEditChangeRequest::where('school_id', $this->school->id)
            ->with(['student:id,name,reg_no'])
            ->latest()
            ->paginate(20);

        return $this->inertia('School/Students/ChangeRequests', [
            'requests'        => $requests,
            'studentEditLock' => app(StudentEditLockService::class)->metaForSchool($this->school),
        ]);
    }

    public function showPhoto(string $tenantId, string $studentId)
    {
        $student = Student::query()
            ->where('tenant_id', $this->school->id)
            ->findOrFail($studentId);

        abort_unless($student->photo, 404);

        try {
            return TenantStorage::downloadResponse($this->school, $student->photo);
        } catch (\Throwable) {
            abort(404, 'Photo not found.');
        }
    }

    public function importForm()
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        return redirect("/school-admin/{$this->school->id}/students?import=1");
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

    public function importStore(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        app(StudentEditLockService::class)->assertEditable($this->school);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $backup = app(UploadBackupService::class)->store(
            $file,
            'student_import',
            $this->school->id,
            null,
            $request->user()->id,
        );

        $result = (new StudentCsvImporter($this->school))->import($file);

        app(DataChangeLogger::class)->event(
            'imported',
            "Student CSV import: {$result['imported']} added, {$result['skipped']} skipped",
            $this->school->id,
            'students',
            null,
            [
                'imported'  => $result['imported'],
                'skipped'   => $result['skipped'],
                'errors'    => count($result['errors']),
                'backup_id' => $backup->id,
            ],
        );

        if ($result['imported'] === 0 && $result['errors'] !== []) {
            return back()->with('importResult', $result)->with('error', 'Import failed. Fix the errors below and try again.');
        }

        $message = "Imported {$result['imported']} student(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} row(s) skipped.";
        }

        return back()
            ->with('success', $message)
            ->with('importResult', $result);
    }

    private function validatedStudentCreate(Request $request): array
    {
        return $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'name'         => 'required|string|max:255',
            'gender'       => 'required|in:male,female,other',
            'dob'          => 'required|date|before:today',
            'email'        => 'nullable|email|max:255',
            'photo'        => 'required|image|max:2048',
            'create_login' => 'boolean',
            'password'     => 'nullable|string|min:8',
        ]);
    }

    /** @param  array<string, mixed>  $fields */
    private function createStudentRecord(array $fields, ?\Illuminate\Http\UploadedFile $photo = null): Student
    {
        return app(StudentRecordCreator::class)->create($this->school, $fields, $photo);
    }

    /** @return array{school_class_id: int, name: string, gender: string, dob: ?string, parent_email: ?string} */
    private function validatedStudentBasicUpdate(Request $request): array
    {
        return $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'name'         => 'required|string|max:255',
            'gender'       => 'required|in:male,female,other',
            'dob'          => 'nullable|date',
            'parent_email' => 'nullable|email|max:255',
            'photo'        => 'nullable|image|max:2048',
        ]);
    }

    private function studentPayload(Student $student): array
    {
        $data = $student->toArray();
        $data['photo_url'] = $student->photoUrl();

        return $data;
    }
}

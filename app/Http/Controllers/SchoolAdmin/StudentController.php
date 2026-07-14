<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\ManagesStudentPortalCredentials;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UploadedFileBackup;
use App\Jobs\ImportStudentsJob;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Portal\StudentPortalProvisioner;
use App\Services\Students\StudentEditChangeService;
use App\Services\Students\StudentEditLockService;
use App\Services\Students\StudentCsvImporter;
use App\Services\Students\StudentRecordCreator;
use App\Services\Students\StudentRegistrationNumberGenerator;
use App\Services\Students\StudentSportsProfileService;
use App\Support\StudentPhotoNaming;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends SchoolAdminController
{
    use ManagesStudentPortalCredentials;

    public function index(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        $filters = $this->validatedStudentListFilters($request);

        $sort = $filters['sort'] ?? 'name';
        $dir  = $filters['dir'] ?? 'asc';

        $query = $this->studentListQuery($filters);

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
                'verification' => 'all',
                'sort'   => 'name',
                'dir'    => 'asc',
            ], $filters),
            'categories' => $this->classCategories()->values(),
            'classes'    => $this->schoolClasses(),
            'classNames' => SchoolClass::where('tenant_id', $this->school->id)->active()->orderBy('display_order')->orderBy('name')->pluck('name')->values(),
            'studentEditLock' => app(StudentEditLockService::class)->metaForSchool($this->school),
            'canManageDirectly' => $this->canManageStudentsDirectly(),
            'unverifiedCount' => Student::where('tenant_id', $this->school->id)
                ->where('status', 'active')
                ->whereNull('verified_at')
                ->count(),
            'missingRegNoCount' => Student::where('tenant_id', $this->school->id)
                ->where('status', 'active')
                ->get(['id', 'reg_no'])
                ->filter(fn (Student $s) => app(StudentRegistrationNumberGenerator::class)->isMissingOrLegacy($s))
                ->count(),
            'pendingChangeRequests' => \App\Models\StudentEditChangeRequest::where('school_id', $this->school->id)
                ->where('status', 'pending')
                ->count(),
        ]);
    }

    public function show(string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);

        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        $student->load([
            'schoolClass.classCategory',
            'schoolHouse',
            'user:id,username,plain_password',
            'verifiedBy:id,name,email',
        ]);

        $sportsProfile = app(StudentSportsProfileService::class)->forStudent($student, $this->school->id);

        return $this->inertia('School/Students/Show', [
            'student'           => $this->profilePayload($student),
            'classes'           => $this->schoolClasses(),
            'sportsProfile'     => $sportsProfile,
            'studentEditLock'   => app(StudentEditLockService::class)->metaForSchool($this->school),
            'canManageDirectly' => $this->canManageStudentsDirectly(),
            'portalLoginUrl'    => url('/portal/login'),
        ]);
    }

    public function backfillRegNumbers(StudentRegistrationNumberGenerator $generator)
    {
        $assigned = 0;
        $errors = [];

        Student::query()
            ->where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->each(function (Student $student) use ($generator, &$assigned, &$errors) {
                if (! $generator->isMissingOrLegacy($student)) {
                    return;
                }

                try {
                    $generator->syncIdentity($student, $this->school);
                    $assigned++;
                } catch (\Throwable $e) {
                    $errors[] = "{$student->name}: {$e->getMessage()}";
                }
            });

        if ($assigned === 0 && $errors === []) {
            return back()->with('success', 'All students already have formatted student IDs.');
        }

        $message = "Assigned student IDs to {$assigned} record(s).";
        if ($errors !== []) {
            $message .= ' Some failed: '.implode(' ', array_slice($errors, 0, 2));
        }

        return back()->with($errors === [] ? 'success' : 'warning', $message);
    }

    public function create()
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        $this->assertCanAddStudents();

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

        $this->assertCanAddStudents();

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

        $this->assertCanAddStudents();

        $data = $this->validatedStudentCreate($request);

        $this->createStudentRecord($data, $request->file('photo'));

        return back()->with('success', 'Student registered successfully. Sahodaya will verify the record and manage portal access.');
    }

    public function storeBulk(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        $this->assertCanAddStudents();

        $data = $request->validate([
            'students' => 'required|array|min:1|max:25',
            'students.*.school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'students.*.name'    => 'required|string|max:255',
            'students.*.gender'  => 'required|in:male,female,other',
            'students.*.dob'     => 'required|date|before:today',
            'students.*.photo'   => 'required|image|max:2048',
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

        return $this->provisionStudentPortalLogin($student);
    }

    public function resetPortalPassword(string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);

        return $this->resetStudentPortalPassword($student, request()->user()?->id);
    }

    /** @return array<string, mixed> */
    private function portalCredentialsFlash(Student $student, ?string $password = null): array
    {
        $provisioner = app(StudentPortalProvisioner::class);
        $student = $student->fresh();

        $result = filled($password)
            ? $provisioner->provision($student, $password)
            : $provisioner->ensureRegNoLogin($student);

        if (! $result['password']) {
            return [];
        }

        $fresh = $student->fresh();

        return [
            'newCredentials' => $this->studentPortalCredentialsPayload($fresh, $result['password']),
        ];
    }

    public function edit(string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);

        return redirect("/school-admin/{$this->school->id}/students/{$student->id}?edit=1");
    }

    public function updatePhoto(Request $request, string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        $this->assertCanEditStudents();

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

        $this->markStudentUnverified($student);

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
        $this->assertCanEditStudents();

        if ($request->hasFile('photos')) {
            $request->validate([
                'photos'   => 'required|array|min:1|max:200',
                'photos.*' => 'required|image|max:5120',
            ]);

            $results = collect($request->file('photos'))
                ->map(fn (UploadedFile $photo) => $this->processUploadedPhotoFile($photo))
                ->all();
        } else {
            $results = [];
        }

        if ($request->hasFile('zip')) {
            $request->validate([
                'zip' => 'required|file|mimes:zip|max:51200',
            ]);

            $results = array_merge($results, $this->processPhotoZipFile($request->file('zip')));
        }

        if ($request->hasFile('photos') || $request->hasFile('zip')) {
            return $this->finishPhotoBulkUpload($results);
        }

        abort(422, 'Choose one or more photos, or upload a ZIP file.');
    }

    /** @return list<array{status: string, label: string, student?: string, match?: string}> */
    private function processPhotoZipFile(UploadedFile $zipFile): array
    {
        abort_if($zipFile->getSize() === 0, 422, 'The uploaded ZIP file is empty.');

        $zipPath = $zipFile->getRealPath() ?: $zipFile->getPathname();
        abort_unless(is_string($zipPath) && $zipPath !== '' && is_readable($zipPath), 422, 'Could not read the uploaded ZIP file.');

        $zip = new \ZipArchive;
        abort_unless($zip->open($zipPath) === true, 422, 'Could not open zip file.');

        $results = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! $entry || str_ends_with($entry, '/') || str_contains($entry, '__MACOSX') || str_ends_with($entry, '.DS_Store')) {
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION) ?: 'jpg');
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $results[] = ['status' => 'skipped', 'label' => $entry];

                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $results[] = ['status' => 'skipped', 'label' => $entry];

                continue;
            }

            $basename = pathinfo(str_replace('\\', '/', $entry), PATHINFO_FILENAME);
            if ($basename === '') {
                $results[] = ['status' => 'skipped', 'label' => $entry];

                continue;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'student-photo-');
            file_put_contents($tmp, $contents);

            $uploaded = new UploadedFile(
                $tmp,
                $basename.'.'.$ext,
                mime_content_type($tmp) ?: 'image/jpeg',
                null,
                true,
            );

            $results[] = $this->processUploadedPhotoFile($uploaded, $entry);
            @unlink($tmp);
        }

        $zip->close();

        return $results;
    }

    /** @return array{status: string, label: string, student?: string, match?: string} */
    private function processUploadedPhotoFile(UploadedFile $photo, ?string $label = null): array
    {
        $label ??= $photo->getClientOriginalName();
        $resolved = StudentPhotoNaming::resolveStudent($this->schoolStudentsForPhotoMatching(), $label);

        if (! $resolved) {
            return ['status' => 'skipped', 'label' => $label];
        }

        try {
            $this->attachPhotoToStudent($resolved['student'], $photo);
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'skipped', 'label' => $label, 'error' => $e->getMessage()];
        }

        return [
            'status'  => 'updated',
            'label'   => $label,
            'student' => $resolved['student']->name,
            'match'   => $resolved['match'],
        ];
    }

    /** @param  list<array{status: string, label: string, student?: string, match?: string}>  $results */
    private function finishPhotoBulkUpload(array $results)
    {
        $updated = collect($results)->where('status', 'updated');
        $skipped = collect($results)->where('status', 'skipped');
        $byName = $updated->where('match', 'name')->count();
        $byId = $updated->where('match', 'id')->count();

        if ($updated->isEmpty()) {
            $failed = $skipped->first(fn ($row) => ! empty($row['error']));
            $storageHint = $failed['error'] ?? null;

            return back()->with('error',
                ($storageHint ? $storageHint.' ' : '')
                .'No photos were matched or saved. Copy each student\'s ID (e.g. STU/26/0006), paste it as the image filename, and add .jpg — '
                .'your computer may save it as STU_26_0006.jpg, which also works. '
                .$skipped->count().' file(s) could not be processed.'
            );
        }

        $parts = ["Updated {$updated->count()} student photo(s)."];
        $names = $updated->pluck('student')->filter()->unique()->values();
        if ($names->isNotEmpty()) {
            $parts[] = $names->take(5)->join(', ').($names->count() > 5 ? '…' : '');
        }
        if ($byName > 0) {
            $parts[] = "{$byName} matched by name";
        }
        if ($byId > 0) {
            $parts[] = "{$byId} matched by student ID";
        }
        if ($skipped->isNotEmpty()) {
            $parts[] = $skipped->count().' skipped';
        }

        return back()->with('success', implode(' · ', $parts).'.');
    }

    /** @return Collection<int, Student> */
    private function schoolStudentsForPhotoMatching(): Collection
    {
        return Student::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->get(['id', 'name', 'reg_no', 'admission_number', 'photo']);
    }

    private function attachPhotoToStudent(Student $student, UploadedFile $photo): void
    {
        $path = TenantStorage::storeStudentPhoto($photo, $this->school->id);

        $student->update(['photo' => $path]);

        $this->markStudentUnverified($student);
    }

    public function photoNamingList(): StreamedResponse
    {
        $rows = Student::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->whereNotNull('reg_no')
            ->orderBy('name')
            ->get(['name', 'reg_no', 'photo']);

        $filename = 'student-photo-filenames-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student_name', 'student_id', 'photo_filename', 'photo_filename_by_name', 'has_photo']);
            foreach ($rows as $student) {
                $row = StudentPhotoNaming::namingRow($student);
                if (! $row) {
                    continue;
                }
                fputcsv($out, [
                    $row['name'],
                    $row['reg_no'],
                    $row['photo_filename'],
                    $row['photo_filename_by_name'],
                    $row['has_photo'] ? 'yes' : 'no',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function update(Request $request, string $tenantId, Student $student)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        $this->assertCanEditStudents();

        $data = $this->validatedStudentBasicUpdate($request, $student);
        if (array_key_exists('admission_number', $data)) {
            $data['admission_number'] = filled($data['admission_number'] ?? null)
                ? trim((string) $data['admission_number'])
                : null;
        }
        $before = $student->only(array_keys($data));

        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
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

        $this->markStudentUnverified($student);

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
        $this->assertCanEditStudents();

        $this->withdrawStudent($student);

        return back()->with('success', 'Student record withdrawn.');
    }

    /**
     * Withdraw selected students, or all active students in a class (wrong upload cleanup).
     */
    public function bulkDestroy(Request $request)
    {
        $this->assertCanEditStudents();

        $data = $request->validate([
            'scope'             => 'required|in:selected,class,all',
            'student_ids'       => 'nullable|array|max:500',
            'student_ids.*'     => 'integer',
            'school_class_id'   => [
                'nullable',
                'integer',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'confirm_school_name' => 'required_if:scope,all|string',
        ]);

        $query = Student::query()->where('tenant_id', $this->school->id);

        if ($data['scope'] === 'class') {
            abort_unless(! empty($data['school_class_id']), 422, 'Choose a class to remove students from.');

            $query->where('school_class_id', $data['school_class_id'])
                ->where('status', 'active');
        } elseif ($data['scope'] === 'all') {
            // Extra server-side guard on top of the frontend confirmation: the typed
            // name must match this school's name exactly (case-insensitive) before
            // every active student in the school gets withdrawn in one go.
            abort_unless(
                mb_strtolower(trim($data['confirm_school_name'])) === mb_strtolower(trim($this->school->name)),
                422,
                'Type the school name exactly to confirm removing all students.'
            );

            $query->where('status', 'active');
        } else {
            $ids = array_values(array_unique(array_map('intval', $data['student_ids'] ?? [])));
            abort_if($ids === [], 422, 'Select at least one student to remove.');

            $query->whereIn('id', $ids);
        }

        $students = $query->orderBy('id')->get();
        $count = 0;

        foreach ($students as $student) {
            $this->withdrawStudent($student);
            $count++;
        }

        if ($count === 0) {
            return back()->with('info', 'No matching students to remove.');
        }

        return back()->with(
            'success',
            $count === 1 ? '1 student withdrawn.' : "{$count} students withdrawn."
        );
    }

    private function withdrawStudent(Student $student): void
    {
        $snapshot = $student->only(['id', 'name', 'school_class_id', 'status', 'parent_email']);
        $name = $student->name;

        $student->update(['status' => 'withdrawn']);
        $student->delete();

        app(DataChangeLogger::class)->deleted(
            $student,
            "Student withdrawn: {$name}",
            $this->school->id,
            'students',
            $snapshot,
        );
    }

    public function export(Request $request)
    {
        $filters = $this->validatedStudentListFilters($request);

        $students = $this->studentListQuery($filters)
            ->with(['schoolClass.classCategory', 'verifiedBy:id,name,email', 'user:id,email'])
            ->orderBy('name')
            ->get();

        $header = [
            'Reg No', 'Admission No', 'Name', 'Class', 'Category', 'Gender', 'DOB',
            'Parent Email', 'Status', 'Verification', 'Verified At', 'Verified By', 'Portal Login', 'Has Photo',
        ];
        $rows = [$header];

        foreach ($students as $student) {
            $rows[] = [
                $student->reg_no ?? '',
                $student->admission_number ?? '',
                $student->name,
                $student->schoolClass?->name ?? '',
                $student->schoolClass?->classCategory?->label ?? '',
                $student->gender ?? '',
                $student->dob?->format('Y-m-d') ?? '',
                $student->parent_email ?? $student->email ?? '',
                $student->status,
                $student->isVerified() ? 'Verified' : 'Unverified',
                $student->verified_at?->format('Y-m-d H:i') ?? '',
                $student->verifiedBy?->name ?? $student->verifiedBy?->email ?? '',
                $student->user_id ? ($student->user?->email ?? 'yes') : 'no',
                $student->photo ? 'yes' : 'no',
            ];
        }

        $prefix = $this->school->school_prefix ?: 'school';
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $filename = "{$prefix}-students-".now()->format('Y-m-d').".{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $xlsx = \App\Services\Spreadsheet\SpreadsheetWriter::xlsx($rows);

        return response()->streamDownload(
            fn () => print $xlsx,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validatedStudentListFilters($request);

        $students = $this->studentListQuery($filters)
            ->with(['schoolClass.classCategory', 'verifiedBy:id,name,email'])
            ->orderBy('name')
            ->get();

        $rows = $students->map(fn (Student $s) => [
            'name'         => $s->name,
            'reg_no'       => $s->reg_no ?? '—',
            'class'        => $s->schoolClass?->name ?? '—',
            'gender'       => $s->gender ? ucfirst($s->gender) : '—',
            'dob'          => $s->dob?->format('d M Y') ?? '—',
            'parent_email' => $s->parent_email ?? $s->email ?? '—',
            'status'       => ucfirst($s->status),
            'verification' => $s->isVerified() ? 'Verified' : 'Pending',
        ])->all();

        $prefix = $this->school->school_prefix ?: 'school';
        $filename = "{$prefix}-students-".now()->format('Y-m-d').'.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('school.students.roster', [
            'school' => $this->school,
            'rows'   => $rows,
        ])->setPaper('a4', 'landscape')->download($filename);
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

        return redirect("/school-admin/{$this->school->id}/students?bulk=1");
    }

    public function importTemplate(Request $request): StreamedResponse
    {
        $importer = new StudentCsvImporter($this->school);

        if ($request->query('format') === 'csv') {
            $csv = $importer->templateCsvForSchool();

            return response()->streamDownload(
                fn () => print("\xEF\xBB\xBF".$csv),
                'student-import-sample.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        $xlsx = $importer->templateXlsxForSchool();

        return response()->streamDownload(
            fn () => print $xlsx,
            'student-import-sample.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function importPreview(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        $this->assertCanAddStudents();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ]);

        $file = $request->file('file');
        $this->assertImportFileReadable($file);
        $tmp = $this->importUploadPath($file);
        $importer = new StudentCsvImporter($this->school);
        $preview = $importer->previewFromPath($tmp);
        $preview['row_count'] = $importer->countDataRows($tmp);

        return response()->json($preview);
    }

    public function importStore(Request $request)
    {
        if (! filled($this->school->school_prefix)) {
            return redirect("/school-admin/{$this->school->id}/setup/code");
        }

        $this->assertCanAddStudents();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ]);

        $file = $request->file('file');
        $this->assertImportFileReadable($file);
        $tmp = $this->importUploadPath($file);

        $importer = new StudentCsvImporter($this->school);
        $rowCount = $importer->countDataRows($tmp);
        $threshold = (int) config('erp.async_import_threshold', 500);

        $backup = app(UploadBackupService::class)->storeFromPath(
            $tmp,
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            'student_import',
            $this->school->id,
            null,
            $request->user()->id,
        );

        if ($rowCount > $threshold) {
            ImportStudentsJob::dispatch(
                $this->school->id,
                $backup->storage_path,
                $request->user()->id,
                $backup->id,
                $backup->storage_disk,
            );

            return back()->with(
                'success',
                "Import queued ({$rowCount} rows). You will be notified when it completes.",
            );
        }

        $result = $importer->importFromPath($tmp);

        $backup->update([
            'status'         => $result['success'] ? UploadedFileBackup::STATUS_SUCCESS : UploadedFileBackup::STATUS_FAILED,
            'total_rows'     => $result['imported'] + $result['skipped'],
            'imported_count' => $result['imported'],
            'error_count'    => count($result['errors']),
            'errors'         => array_slice($result['errors'], 0, 50),
        ]);

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

        if (! $result['success']) {
            return back()->with('importResult', $result)->with('error', 'Import rejected: fix the error(s) below and re-upload. Nothing was imported.');
        }

        return back()
            ->with('success', "Imported {$result['imported']} student(s).")
            ->with('importResult', $result);
    }

    private function validatedStudentCreate(Request $request): array
    {
        return $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'name'              => 'required|string|max:255',
            'gender'            => 'required|in:male,female,other',
            'dob'               => 'required|date|before:today',
            'email'             => 'nullable|email|max:255',
            'admission_number'  => $this->admissionNumberRules(),
            'photo'             => 'required|image|max:2048',
        ]);
    }

    /** @param  array<string, mixed>  $fields */
    private function createStudentRecord(array $fields, ?\Illuminate\Http\UploadedFile $photo = null): Student
    {
        return app(StudentRecordCreator::class)->create($this->school, $fields, $photo);
    }

    /** @return array{school_class_id: int, name: string, gender: string, dob: ?string, parent_email: ?string, admission_number?: ?string} */
    private function validatedStudentBasicUpdate(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'name'             => 'required|string|max:255',
            'gender'           => 'required|in:male,female,other',
            'dob'              => 'nullable|date|before_or_equal:today',
            'parent_email'     => 'nullable|email|max:255',
            'admission_number' => $this->admissionNumberRules($student),
            'photo'            => 'nullable|image|max:2048',
        ]);
    }

    /** @return list<mixed> */
    private function admissionNumberRules(?Student $ignore = null): array
    {
        $unique = Rule::unique('students', 'admission_number')
            ->where(fn ($q) => $q->where('tenant_id', $this->school->id)->whereNull('deleted_at'));

        if ($ignore) {
            $unique->ignore($ignore->id);
        }

        return ['nullable', 'string', 'max:50', $unique];
    }

    private function studentPayload(Student $student): array
    {
        $data = $student->toArray();
        $data['photo_url'] = $student->photoUrl();
        $data['has_photo'] = filled($student->photo);
        $data['is_verified'] = $student->isVerified();
        $data['verified_at'] = $student->verified_at?->toIso8601String();

        return $data;
    }

    /** @return array<string, mixed> */
    private function profilePayload(Student $student): array
    {
        return [
            'id'               => $student->id,
            'school_class_id'  => $student->school_class_id,
            'name'             => $student->name,
            'reg_no'            => $student->reg_no,
            'admission_number'  => $student->admission_number,
            'roll_number'       => $student->roll_number,
            'gender'         => $student->gender,
            'dob'            => $student->dob?->format('Y-m-d'),
            'dob_display'    => $student->dob?->format('j M Y'),
            'age_years'      => $student->dob ? (int) $student->dob->diffInYears(now()) : null,
            'blood_group'    => $student->blood_group,
            'email'          => $student->email,
            'parent_name'    => $student->parent_name,
            'parent_phone'   => $student->parent_phone,
            'parent_email'   => $student->parent_email,
            'address'        => $student->address,
            'admission_date' => $student->admission_date?->format('Y-m-d'),
            'status'         => $student->status,
            'notes'          => $student->notes,
            'class_name'     => $student->schoolClass?->name,
            'category_label' => $student->schoolClass?->classCategory?->label,
            'house_name'     => $student->schoolHouse?->name,
            'is_verified'    => $student->isVerified(),
            'verified_at'    => $student->verified_at?->toIso8601String(),
            'verified_by'    => $student->verifiedBy?->name ?? $student->verifiedBy?->email,
            'has_portal_login' => (bool) $student->user_id,
            'portal_username'  => $student->user?->username ?? $student->reg_no,
            'portal_password'  => $student->user?->plain_password,
            'photo_url'      => $student->photoUrl(),
            'has_photo'      => filled($student->photo),
        ];
    }

    private function markStudentUnverified(Student $student): void
    {
        if ($student->verified_at === null) {
            return;
        }

        $student->update([
            'verified_at'         => null,
            'verified_by_user_id' => null,
            'rejection_reason'    => null,
        ]);

        $notificationService = app(\App\Services\Notifications\NotificationService::class);
        $replacements = ['student_name' => $student->name];

        foreach (\App\Models\User::role(['school_admin', 'school_staff'])->where('tenant_id', $this->school->id)->get() as $user) {
            $notificationService->notifyFromTemplate(
                $user,
                'student.verification.required',
                $replacements,
                '/school-admin/'.$this->school->id.'/students',
            );
        }

        if ($this->school->parent_id) {
            app(\App\Services\Notifications\SahodayaAdminNotifier::class)->notifyAdmins(
                $this->school->parent_id,
                'student.verification.pending',
                $replacements,
                "/sahodaya-admin/{$this->school->parent_id}/students/verification",
            );
        }
    }

    /** @return array<string, mixed> */
    private function validatedStudentListFilters(Request $request): array
    {
        return $request->validate([
            'class_category_id' => 'nullable|integer',
            'school_class_id'   => 'nullable|integer',
            'status'            => 'nullable|in:active,transferred,graduated,withdrawn,all',
            'verification'      => 'nullable|in:all,verified,unverified',
            'search'            => 'nullable|string|max:100',
            'sort'              => 'nullable|in:name,parent_email,status,class,reg_no,verified_at',
            'dir'               => 'nullable|in:asc,desc',
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    private function studentListQuery(array $filters)
    {
        return Student::where('tenant_id', $this->school->id)
            ->with(['schoolClass.classCategory'])
            ->when(! empty($filters['class_category_id']), function ($q) use ($filters) {
                $q->whereHas('schoolClass', fn ($c) => $c->where('class_category_id', $filters['class_category_id']));
            })
            ->when(! empty($filters['school_class_id']), fn ($q) => $q->where('school_class_id', $filters['school_class_id']))
            ->when(($filters['status'] ?? 'active') !== 'all', function ($q) use ($filters) {
                $q->where('status', $filters['status'] ?? 'active');
            })
            ->when(($filters['verification'] ?? 'all') === 'verified', fn ($q) => $q->whereNotNull('verified_at'))
            ->when(($filters['verification'] ?? 'all') === 'unverified', fn ($q) => $q->whereNull('verified_at'))
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
    }

    private function importUploadPath(UploadedFile $file): string
    {
        $path = $file->getRealPath() ?: $file->getPathname();

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'file' => 'The upload could not be read. Choose the file again and retry.',
            ]);
        }

        return $path;
    }

    private function assertImportFileReadable(UploadedFile $file): void
    {
        if ($file->getSize() === 0) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is empty. Re-download the template or re-save your spreadsheet, then choose the file again.',
            ]);
        }
    }
}

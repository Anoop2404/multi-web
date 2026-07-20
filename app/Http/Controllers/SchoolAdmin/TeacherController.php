<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\ManagesTeacherPortalCredentials;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\UploadedFileBackup;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Auth\EmployeeCodeGenerator;
use App\Services\Auth\LoginCodeGenerator;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Portal\TeacherPortalProvisioner;
use App\Services\Spreadsheet\SpreadsheetReader;
use App\Services\Spreadsheet\SpreadsheetWriter;
use App\Support\AcademicYear;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeacherController extends SchoolAdminController
{
    use ManagesTeacherPortalCredentials;

    public function index(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $sahodayaId = $this->school->parent_id;

        $subjectLabelMap = $resolver->subjects($sahodayaId)->pluck('label', 'id');

        $filters = $this->validatedTeacherListFilters($request);
        $sort = $filters['sort'] ?? 'name';
        $dir = $filters['dir'] ?? 'asc';

        $teachers = $this->teacherListQuery($filters)
            ->with(['teachingType', 'schoolClasses', 'user:id,username,plain_password,email'])
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString()
            ->through(function (Teacher $t) use ($subjectLabelMap) {
                $subjectIds = $t->subject_ids ?? [];

                return [
                    ...$t->only('id', 'name', 'email', 'login_code', 'employee_code', 'mobile', 'designation', 'designation_id', 'subject', 'user_id', 'teaching_type_id', 'status'),
                    'gender' => $t->gender,
                    'is_verified' => $t->isVerified(),
                    'teaching_type' => $t->teachingType?->label,
                    'subject_labels' => collect($subjectIds)->map(fn ($id) => $subjectLabelMap->get($id))->filter()->values()->all(),
                    'subject_ids' => array_map('intval', $subjectIds),
                    'photo_url' => $t->photoUrl(),
                    'portal_email' => $t->user?->email,
                    'portal_username' => $t->login_code ?? $t->user?->username,
                    'portal_password' => $t->user?->plain_password,
                ];
            });

        return $this->inertia('School/Teachers/Index', [
            'teachers'     => $teachers,
            'filters'      => array_merge([
                'status' => 'active',
                'verification' => 'all',
                'teaching_type_id' => '',
                'search' => '',
                'sort'   => 'name',
                'dir'    => 'asc',
            ], $filters),
            'teachingTypes'=> $resolver->teachingTypes($sahodayaId),
            'designations' => $resolver->designations($sahodayaId),
            'subjects'     => $resolver->subjects($sahodayaId),
            'schoolClasses'=> SchoolClass::where('tenant_id', $this->school->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedTeacherListFilters(Request $request): array
    {
        return $request->validate([
            'teaching_type_id' => 'nullable|integer',
            'status'           => 'nullable|in:active,inactive,all',
            'verification'     => 'nullable|in:all,verified,unverified',
            'search'           => 'nullable|string|max:100',
            'sort'             => 'nullable|in:name,email,status',
            'dir'              => 'nullable|in:asc,desc',
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    private function teacherListQuery(array $filters)
    {
        return Teacher::where('tenant_id', $this->school->id)
            ->when(! empty($filters['teaching_type_id']), fn ($q) => $q->where('teaching_type_id', $filters['teaching_type_id']))
            ->when(($filters['status'] ?? 'active') !== 'all', function ($q) use ($filters) {
                $q->where('status', $filters['status'] ?? 'active');
            })
            ->when(($filters['verification'] ?? 'all') === 'verified', fn ($q) => $q->whereNotNull('verified_at'))
            ->when(($filters['verification'] ?? 'all') === 'unverified', fn ($q) => $q->whereNull('verified_at'))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('login_code', 'like', $term)
                        ->orWhere('mobile', 'like', $term);
                });
            });
    }

    public function export(Request $request)
    {
        $filters = $this->validatedTeacherListFilters($request);

        $teachers = $this->teacherListQuery($filters)
            ->with(['teachingType', 'verifiedBy:id,name,email'])
            ->orderBy('name')
            ->get();

        $subjectLabelMap = app(EffectiveMasterDataResolver::class)->subjects($this->school->parent_id)->pluck('label', 'id');

        $header = ['Name', 'Teacher ID', 'Employee Code', 'Email', 'Mobile', 'Designation', 'Teaching Type', 'Subjects', 'Status', 'Verification', 'Verified By'];
        $rows = [$header];

        foreach ($teachers as $t) {
            $subjectLabels = collect($t->subject_ids ?? [])->map(fn ($id) => $subjectLabelMap->get($id))->filter()->implode('; ');

            $rows[] = [
                $t->name,
                $t->login_code ?? '',
                $t->employee_code ?? '',
                $t->email ?? '',
                $t->mobile ?? '',
                $t->designation ?? '',
                $t->teachingType?->label ?? '',
                $subjectLabels,
                $t->status,
                $t->isVerified() ? 'Verified' : 'Pending',
                $t->verifiedBy?->name ?? '',
            ];
        }

        $prefix = $this->school->school_prefix ?: 'school';
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $filename = "{$prefix}-teachers-".now()->format('Y-m-d').".{$format}";

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

        $xlsx = SpreadsheetWriter::xlsx($rows);

        return response()->streamDownload(
            fn () => print $xlsx,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validatedTeacherListFilters($request);

        $teachers = $this->teacherListQuery($filters)
            ->with(['teachingType', 'verifiedBy:id,name,email'])
            ->orderBy('name')
            ->get();

        $subjectLabelMap = app(EffectiveMasterDataResolver::class)->subjects($this->school->parent_id)->pluck('label', 'id');

        $rows = $teachers->map(fn (Teacher $t) => [
            'name'           => $t->name,
            'login_code'     => $t->login_code ?? '—',
            'employee_code'  => $t->employee_code ?? '—',
            'teaching_type'  => $t->teachingType?->label ?? '—',
            'subjects'       => collect($t->subject_ids ?? [])->map(fn ($id) => $subjectLabelMap->get($id))->filter()->implode(', ') ?: '—',
            'mobile'         => $t->mobile ?? '—',
            'email'          => $t->email ?? '—',
            'status'         => ucfirst($t->status),
            'verification'   => $t->isVerified() ? 'Verified' : 'Pending',
        ])->all();

        $prefix = $this->school->school_prefix ?: 'school';
        $filename = "{$prefix}-teachers-".now()->format('Y-m-d').".pdf";

        $sahodaya = Tenant::find($this->school->parent_id);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('school.teachers.roster', [
            'school'  => $this->school,
            'rows'    => $rows,
            'orgName' => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc' => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    public function store(Request $request, PlatformAuditLogger $audit)
    {
        $data = $this->validatedTeacher($request);

        $yearId = AcademicYear::activeId();

        $photoPath = $request->hasFile('photo')
            ? TenantStorage::storeTeacherPhoto($request->file('photo'), $this->school->id)
            : null;

        $teacher = Teacher::create([
            'tenant_id'         => $this->school->id,
            'academic_year_id'  => $yearId,
            'name'              => $data['name'],
            'gender'            => $data['gender'] ?? null,
            'dob'               => $data['dob'] ?? null,
            'email'             => strtolower(trim($data['email'])),
            'mobile'            => $data['mobile'],
            'photo'             => $photoPath,
            'address'           => $data['address'] ?? null,
            'designation'       => $data['designation'] ?? null,
            'designation_id'    => $data['designation_id'] ?? null,
            // 'subject' is intentionally not set here — it's a denormalized label
            // string derived from subject_ids and must only ever be written by
            // Teacher::syncSubjectIds() (called just below via syncRelations()),
            // otherwise it can silently drift from subject_ids.
            'teaching_type_id'  => $data['teaching_type_id'],
            'qualification'     => $data['qualification'] ?? null,
            'experience_years'  => $data['experience_years'] ?? null,
            'date_of_joining'   => $data['date_of_joining'] ?? null,
            'employment_status' => $data['employment_status'] ?? null,
            'status'            => 'active',
        ]);

        $this->syncRelations($teacher, $data);

        app(LoginCodeGenerator::class)->assignTeacher($teacher);
        app(EmployeeCodeGenerator::class)->assign($teacher);

        $audit->log('teacher.created', "Teacher created: {$teacher->name}", $teacher);

        $credentials = null;
        if ($request->boolean('create_login', true)) {
            $result = app(TeacherPortalProvisioner::class)->provision(
                $teacher->fresh(),
                $data['email'],
                $data['password'] ?? null,
            );
            $credentials = $this->teacherPortalCredentialsPayload($teacher->fresh(), $result['password']);
        }

        return back()->with(array_filter([
            'success'        => 'Teacher added.',
            'newCredentials' => $credentials,
        ]));
    }

    /**
     * Structured-error, transactional all-or-nothing commit — matches importStore()'s
     * pattern instead of the old silent skip-and-continue behaviour. Any row error
     * rejects the whole batch (nothing is partially created).
     */
    public function storeBulk(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $data = $request->validate([
            'teachers'                    => 'required|array|min:1|max:50',
            'teachers.*.name'             => 'required|string|max:255',
            'teachers.*.email'            => 'required|email|max:255',
            'teachers.*.mobile'           => 'required|string|max:20',
            'teachers.*.gender'           => 'nullable|in:male,female,other',
            'teachers.*.teaching_type_id' => 'required|integer',
            'teachers.*.designation_id'   => 'nullable|integer',
            'teachers.*.subject_ids'      => 'required|array|min:1',
            'teachers.*.subject_ids.*'    => 'integer',
            'create_logins'               => 'nullable|boolean',
        ]);

        $validSubjectIds = $resolver->subjects($this->school->parent_id)->pluck('id')->all();

        $rows = [];
        $errors = [];
        $seenEmails = [];
        $line = 0;

        foreach ($data['teachers'] as $row) {
            $line++;

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $name = $row['name'];

            $email = filled($row['email'] ?? null) ? strtolower(trim($row['email'])) : null;
            if (! $email) {
                $errors[] = ['row' => $line, 'message' => "{$name}: email is required."];

                continue;
            }

            if (isset($seenEmails[$email])) {
                $errors[] = ['row' => $line, 'message' => "{$name}: duplicate email \"{$email}\" (also used on row {$seenEmails[$email]})."];

                continue;
            }

            if (Teacher::where('email', $email)->exists() || User::where('email', $email)->exists()) {
                $errors[] = ['row' => $line, 'message' => "{$name}: email \"{$email}\" is already registered."];

                continue;
            }

            $seenEmails[$email] = $line;

            $ids = array_values(array_intersect(array_map('intval', $row['subject_ids'] ?? []), $validSubjectIds));
            if ($ids === []) {
                $errors[] = ['row' => $line, 'message' => "{$name}: no recognized subjects."];

                continue;
            }

            $rows[] = [
                'name'             => $name,
                'email'            => $email,
                'mobile'           => $row['mobile'],
                'gender'           => $row['gender'] ?? null,
                'teaching_type_id' => $row['teaching_type_id'],
                'designation_id'   => $row['designation_id'] ?? null,
                'subject_ids'      => $ids,
            ];
        }

        if ($errors !== []) {
            return back()->with('bulkErrors', $errors)->with('error', 'Nothing was added: fix the error(s) below and resubmit.');
        }

        if ($rows === []) {
            return back()->with('error', 'No teachers to add — fill in at least one row.');
        }

        $createLogins = $request->boolean('create_logins', true);

        try {
            $created = DB::transaction(function () use ($rows, $createLogins) {
                $yearId = AcademicYear::activeId();
                $count = 0;

                foreach ($rows as $row) {
                    $teacher = Teacher::create([
                        'tenant_id'        => $this->school->id,
                        'academic_year_id' => $yearId,
                        'name'             => $row['name'],
                        'email'            => $row['email'],
                        'mobile'           => $row['mobile'],
                        'gender'           => $row['gender'],
                        'teaching_type_id' => $row['teaching_type_id'],
                        'designation_id'   => $row['designation_id'],
                        'status'           => 'active',
                    ]);

                    $teacher->syncSubjectIds($row['subject_ids']);

                    app(LoginCodeGenerator::class)->assignTeacher($teacher);
                    app(EmployeeCodeGenerator::class)->assign($teacher);

                    if ($createLogins) {
                        app(TeacherPortalProvisioner::class)->provision($teacher->fresh(), $row['email']);
                    }

                    $count++;
                }

                return $count;
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Add teachers failed and was rolled back: '.$e->getMessage());
        }

        return back()->with('success', "{$created} teacher(s) added.");
    }

    public function importTemplate(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $header = ['name', 'email', 'mobile', 'gender', 'designation', 'teaching_type', 'subjects', 'qualification', 'date_of_joining'];

        $designation = $resolver->designations($this->school->parent_id)->first()?->label ?? 'PGT';
        $teachingType = $resolver->teachingTypes($this->school->parent_id)->first()?->label ?? 'Trained Graduate Teacher';
        $subjects = $resolver->subjects($this->school->parent_id)->take(2)->pluck('label')->implode('; ') ?: 'Mathematics; Physics';

        $sample = ['Anita Menon', 'anita@school.edu', '9876543210', 'female', $designation, $teachingType, $subjects, 'M.Sc B.Ed', '2020-06-01'];

        if ($request->query('format') === 'csv') {
            $csv = implode(',', $header)."\n".implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $sample))."\n";

            return response()->streamDownload(
                fn () => print("\xEF\xBB\xBF".$csv),
                'teacher-import-sample.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        $xlsx = SpreadsheetWriter::xlsx([$header, $sample]);

        return response()->streamDownload(
            fn () => print $xlsx,
            'teacher-import-sample.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function importStore(Request $request, EffectiveMasterDataResolver $resolver, PlatformAuditLogger $audit)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:5120']);

        $backup = app(UploadBackupService::class)->store(
            $request->file('file'),
            'teacher_import',
            $this->school->id,
            null,
            $request->user()->id,
        );

        $subjectMap = $this->lookupMap($resolver->subjects($this->school->parent_id));
        $typeMap = $this->lookupMap($resolver->teachingTypes($this->school->parent_id));
        $designationMap = $this->lookupMap($resolver->designations($this->school->parent_id));
        $yearId = AcademicYear::activeId();

        $validRows = [];
        $errors = [];
        $seenEmails = [];
        $line = 0;
        $header = null;

        $path = TenantStorage::localTempPath($backup->storage_path, $backup->storage_disk);
        try {
            foreach (SpreadsheetReader::rows($path) as $cols) {
                $line++;

                if ($line === 1) {
                    $header = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $cols);

                    continue;
                }

                $row = [];
                foreach ($header as $i => $key) {
                    $row[$key] = isset($cols[$i]) ? trim((string) $cols[$i]) : null;
                }

                if (blank($row['name'] ?? null)) {
                    continue;
                }

                $name = $row['name'];

                $email = filled($row['email'] ?? null) ? strtolower(trim($row['email'])) : null;
                if (! $email) {
                    $errors[] = ['row' => $line, 'message' => "{$name}: email is required."];

                    continue;
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['row' => $line, 'message' => "{$name}: invalid email \"{$email}\"."];

                    continue;
                }

                if (blank($row['mobile'] ?? null)) {
                    $errors[] = ['row' => $line, 'message' => "{$name}: mobile is required."];

                    continue;
                }

                $typeId = $typeMap[strtolower((string) ($row['teaching_type'] ?? ''))] ?? null;
                if (! $typeId) {
                    $errors[] = ['row' => $line, 'message' => "{$name}: teaching_type \"".($row['teaching_type'] ?? '')."\" is not recognized."];

                    continue;
                }

                $subjectIds = [];
                foreach (preg_split('/[;,]/', (string) ($row['subjects'] ?? '')) as $token) {
                    $token = strtolower(trim($token));
                    if ($token !== '' && isset($subjectMap[$token])) {
                        $subjectIds[] = $subjectMap[$token];
                    }
                }
                if ($subjectIds === []) {
                    $errors[] = ['row' => $line, 'message' => "{$name}: no recognized subjects."];

                    continue;
                }

                if (isset($seenEmails[$email])) {
                    $errors[] = ['row' => $line, 'message' => "{$name}: duplicate email \"{$email}\" (also used on row {$seenEmails[$email]})."];

                    continue;
                }

                if (Teacher::where('email', $email)->exists() || User::where('email', $email)->exists()) {
                    $errors[] = ['row' => $line, 'message' => "{$name}: email \"{$email}\" is already registered."];

                    continue;
                }

                $seenEmails[$email] = $line;

                $validRows[] = [
                    'name'             => $name,
                    'email'            => $email,
                    'mobile'           => $row['mobile'],
                    'gender'           => in_array(strtolower((string) ($row['gender'] ?? '')), ['male', 'female', 'other'], true) ? strtolower($row['gender']) : null,
                    'teaching_type_id' => $typeId,
                    'designation_id'   => $designationMap[strtolower((string) ($row['designation'] ?? ''))] ?? null,
                    'subject_ids'      => array_unique($subjectIds),
                    'qualification'    => $row['qualification'] ?? null,
                    'date_of_joining'  => $this->parseDate($row['date_of_joining'] ?? null),
                ];
            }
        } finally {
            if (str_starts_with($path, sys_get_temp_dir())) {
                @unlink($path);
            }
        }

        if ($header === null) {
            $backup->update(['status' => UploadedFileBackup::STATUS_FAILED, 'errors' => [['row' => 0, 'message' => 'The file is empty.']]]);

            return back()->with('error', 'The file is empty.');
        }

        if ($errors !== []) {
            $backup->update([
                'status'         => UploadedFileBackup::STATUS_FAILED,
                'total_rows'     => count($validRows) + count($errors),
                'imported_count' => 0,
                'error_count'    => count($errors),
                'errors'         => array_slice($errors, 0, 50),
            ]);

            return back()->with('importErrors', $errors)->with('error', 'Import rejected: fix the error(s) below and re-upload. Nothing was imported.');
        }

        if ($validRows === []) {
            $backup->update(['status' => UploadedFileBackup::STATUS_FAILED, 'errors' => [['row' => 0, 'message' => 'The file has no data rows to import.']]]);

            return back()->with('error', 'The file has no data rows to import.');
        }

        try {
            $created = DB::transaction(function () use ($validRows, $yearId) {
                $count = 0;

                foreach ($validRows as $row) {
                    $teacher = Teacher::create([
                        'tenant_id'        => $this->school->id,
                        'academic_year_id' => $yearId,
                        'name'             => $row['name'],
                        'email'            => $row['email'],
                        'mobile'           => $row['mobile'],
                        'gender'           => $row['gender'],
                        'teaching_type_id' => $row['teaching_type_id'],
                        'designation_id'   => $row['designation_id'],
                        'qualification'    => $row['qualification'],
                        'date_of_joining'  => $row['date_of_joining'],
                        'status'           => 'active',
                    ]);

                    $teacher->syncSubjectIds($row['subject_ids']);

                    app(LoginCodeGenerator::class)->assignTeacher($teacher);
                    app(EmployeeCodeGenerator::class)->assign($teacher);
                    app(TeacherPortalProvisioner::class)->provision($teacher->fresh(), $row['email']);

                    $count++;
                }

                return $count;
            });
        } catch (\Throwable $e) {
            $backup->update([
                'status'         => UploadedFileBackup::STATUS_FAILED,
                'total_rows'     => count($validRows),
                'imported_count' => 0,
                'error_count'    => 1,
                'errors'         => [['row' => 0, 'message' => 'Import failed and was rolled back: '.$e->getMessage()]],
            ]);

            return back()->with('error', 'Import failed and was rolled back: '.$e->getMessage());
        }

        $backup->update([
            'status'         => UploadedFileBackup::STATUS_SUCCESS,
            'total_rows'     => $created,
            'imported_count' => $created,
            'error_count'    => 0,
            'errors'         => [],
        ]);

        $audit->log('teacher.imported', "Imported {$created} teacher(s) from CSV", $this->school);

        return back()->with('success', "Imported {$created} teacher(s).");
    }

    /**
     * Build a lower-cased lookup of both code and label → id.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $items
     * @return array<string, int>
     */
    private function lookupMap($items): array
    {
        $map = [];
        foreach ($items as $item) {
            if (filled($item->code ?? null)) {
                $map[strtolower((string) $item->code)] = (int) $item->id;
            }
            if (filled($item->label ?? null)) {
                $map[strtolower((string) $item->label)] = (int) $item->id;
            }
        }

        return $map;
    }

    private function parseDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * School admins may edit a teacher's record freely at any time — unlike Student
     * Registry, there is no Sahodaya-side change-request/edit-lock workflow gating
     * these edits. This is an intentional product decision (not a gap): confirmed
     * with the platform owner to keep Teacher Registry edits unrestricted rather
     * than add edit-lock parity with Student Registry.
     */
    public function update(Request $request, string $tenantId, Teacher $teacher, DataChangeLogger $changes)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $data = $this->validatedTeacher($request, $teacher);
        // 'subject' is a denormalized label string derived from subject_ids — never
        // write it directly, only via Teacher::syncSubjectIds() (see syncRelations()
        // below), otherwise it can silently drift from subject_ids.
        unset($data['subject']);
        $before = $teacher->only(array_keys($data));

        $teacher->update($data);
        $this->syncRelations($teacher, $data);

        if ($teacher->user_id && filled($data['email'])) {
            User::whereKey($teacher->user_id)->update([
                'name'  => $data['name'],
                'email' => strtolower(trim($data['email'])),
            ]);
        }

        $this->markTeacherUnverified($teacher);

        // Single canonical log for this event (DataChangeLogger, with a full before/after
        // diff) — matches StudentController::update()'s pattern. Previously also fired
        // PlatformAuditLogger for the same event; that was a redundant duplicate write.
        $changes->updated($teacher, 'Teacher updated', DataChangeLogger::diff($before, $teacher->only(array_keys($data))), $this->school->id, 'teachers');

        return back()->with('success', 'Teacher updated.');
    }

    /**
     * Any edit to an already-verified teacher resets verification — matches
     * StudentController::markStudentUnverified() and the FRD-02 "re-verification
     * after edits" requirement. No-op for teachers that aren't currently verified.
     */
    private function markTeacherUnverified(Teacher $teacher): void
    {
        if ($teacher->verified_at === null) {
            return;
        }

        $teacher->update([
            'verified_at'         => null,
            'verified_by_user_id' => null,
        ]);

        $notificationService = app(\App\Services\Notifications\NotificationService::class);
        $replacements = ['teacher_name' => $teacher->name];

        // "Required" tells this school their teacher now needs re-verification.
        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $this->school->id)->get() as $user) {
            $notificationService->notifyFromTemplate($user, 'teacher.verification.required', $replacements, '/school-admin/'.$this->school->id.'/teachers');
        }

        // "Pending" puts it back in the Sahodaya reviewer queue.
        app(\App\Services\Notifications\SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'teacher.verification.pending',
            $replacements,
            "/sahodaya-admin/{$this->school->parent_id}/teachers/verification",
        );
    }

    public function provisionPortal(Request $request, string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        return $this->provisionTeacherPortalLogin($teacher, $data['email'], $data['password'] ?? null);
    }

    public function resetPortalPassword(
        string $tenantId,
        Teacher $teacher,
    ) {
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        return $this->resetTeacherPortalPassword($teacher, request()->user()?->id);
    }

    public function destroy(string $tenantId, Teacher $teacher, PlatformAuditLogger $audit)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $teacher->update(['status' => 'inactive']);
        $audit->log('teacher.deactivated', "Teacher deactivated: {$teacher->name}", $teacher);

        return back()->with('success', 'Teacher marked inactive.');
    }

    public function showPhoto(string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);
        abort_unless($teacher->photo, 404);

        try {
            return TenantStorage::downloadResponse($this->school, $teacher->photo);
        } catch (\Throwable) {
            abort(404, 'Photo not found.');
        }
    }

    public function updatePhoto(Request $request, string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        $teacher->update([
            'photo' => TenantStorage::storeTeacherPhoto($request->file('photo'), $this->school->id),
        ]);

        return back()->with('success', 'Teacher photo updated.');
    }

    public function uploadPhotosZip(Request $request, string $tenantId)
    {
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

            $candidates = [
                $basename,
                str_replace('-', '/', $basename),
                str_replace('_', '/', $basename),
            ];

            $teacher = Teacher::where('tenant_id', $this->school->id)
                ->where(function ($q) use ($candidates, $basename) {
                    foreach ($candidates as $code) {
                        $q->orWhere('login_code', $code);
                    }
                    $q->orWhere('email', $basename);
                })
                ->first();

            if (! $teacher) {
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

            $tmp = tempnam(sys_get_temp_dir(), 'teacher-photo-');
            file_put_contents($tmp, $contents);

            $uploaded = new \Illuminate\Http\UploadedFile(
                $tmp,
                $basename.'.'.$ext,
                mime_content_type($tmp) ?: 'image/jpeg',
                null,
                true
            );

            $teacher->update([
                'photo' => TenantStorage::storeTeacherPhoto($uploaded, $this->school->id),
            ]);

            @unlink($tmp);
            $updated++;
        }

        $zip->close();

        return back()->with('success', "Updated {$updated} teacher photo(s). {$skipped} file(s) skipped.");
    }

    /** @return array<string, mixed> */
    private function validatedTeacher(Request $request, ?Teacher $teacher = null): array
    {
        return $request->validate([
            'name'              => 'required|string|max:255',
            'gender'            => 'nullable|in:male,female,other',
            'dob'               => 'nullable|date',
            'email'             => [
                'required',
                'email',
                'max:255',
                Rule::unique('teachers', 'email')->ignore($teacher?->id),
            ],
            'mobile'            => 'required|string|max:20',
            'photo'             => ($teacher ? 'nullable' : 'required').'|image|max:2048',
            'address'           => 'nullable|string|max:1000',
            'designation'       => 'nullable|string|max:255',
            'designation_id'    => 'nullable|integer',
            'subject'           => 'nullable|string|max:255',
            'teaching_type_id'  => 'required|integer',
            'qualification'     => 'nullable|string|max:255',
            'experience_years'  => 'nullable|integer|min:0|max:60',
            'date_of_joining'   => 'nullable|date',
            'employment_status' => 'nullable|in:permanent,contract,temporary,probation',
            'subject_ids'       => 'required|array|min:1',
            'subject_ids.*'     => 'integer',
            'class_assignments' => 'nullable|array',
            'class_assignments.*.school_class_id' => 'required|integer',
            'class_assignments.*.section' => 'nullable|string|max:10',
            'create_login'      => 'boolean',
            'password'          => 'nullable|string|min:8',
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function syncRelations(Teacher $teacher, array $data): void
    {
        if (array_key_exists('subject_ids', $data)) {
            $teacher->syncSubjectIds($data['subject_ids'] ?? []);
        }

        if (array_key_exists('class_assignments', $data)) {
            $sync = [];
            foreach ($data['class_assignments'] ?? [] as $row) {
                $sync[$row['school_class_id']] = ['section' => $row['section'] ?? null];
            }
            $teacher->schoolClasses()->sync($sync);
        }
    }
}

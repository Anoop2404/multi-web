<?php

namespace App\Services\Training;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingPendingSchool;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\SchoolApplicationForm;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingPublicRegistrationService
{
    public function __construct(
        private readonly TrainingQrService $qr,
        private readonly PlatformAuditLogger $audit,
        private readonly TeacherTrainingEligibilityService $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{registration: TrainingRegistration, teacher: Teacher, teacher_created: bool, pending_school: ?TrainingPendingSchool}
     */
    public function register(TrainingProgram $program, array $data, ?UploadedFile $photo = null): array
    {
        if (! $this->qr->isRegistrationOpen($program)) {
            throw ValidationException::withMessages([
                'registration' => 'QR registration is closed for this training programme.',
            ]);
        }

        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $mobile = isset($data['phone']) ? preg_replace('/\D+/', '', (string) $data['phone']) : null;
        $name = trim((string) $data['name']);

        $schoolId = $data['school_id'] ?? null;
        $pendingSchool = null;
        $school = null;

        if ($schoolId) {
            $school = Tenant::query()
                ->where('id', $schoolId)
                ->where('type', 'school')
                ->where('parent_id', $program->tenant_id)
                ->first();

            if (! $school) {
                throw ValidationException::withMessages([
                    'school_id' => 'Selected school was not found under this Sahodaya.',
                ]);
            }
        } else {
            $manualName = trim((string) ($data['manual_school_name'] ?? ''));
            if ($manualName === '') {
                throw ValidationException::withMessages([
                    'manual_school_name' => 'Please select a school or enter your school name.',
                ]);
            }

            $pendingSchool = TrainingPendingSchool::create([
                'program_id'    => $program->id,
                'school_name'   => $manualName,
                'school_code'   => $data['manual_school_code'] ?? null,
                'contact_name'  => $name,
                'contact_email' => $email,
                'contact_phone' => $mobile,
                'status'        => 'pending',
            ]);
        }

        $teacherCreated = false;
        $teacher = $this->findExistingTeacher($program, $school, $email, $mobile, $name);

        if ($teacher) {
            $existing = TrainingRegistration::where('program_id', $program->id)
                ->where('teacher_id', $teacher->id)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'email' => 'This teacher is already registered for this programme.',
                ]);
            }

            $this->refreshExistingTeacherProfile($teacher, $data, $photo);
        } else {
            if (! $school) {
                // Create teacher under Sahodaya tenant temporarily? Teachers belong to schools.
                // Use a placeholder: store under first matching or create with pending school only.
                // FRD: "New teacher -> create in school with Pending School Verification"
                // Without a school, we still need a tenant_id. Use sahodaya id as holding tenant
                // OR require school. Better: create under sahodaya with unverified status until school linked.
                $holdingTenantId = $program->tenant_id;
            } else {
                $holdingTenantId = $school->id;
            }

            $photoPath = null;
            if ($photo) {
                $photoPath = TenantStorage::storeTeacherPhoto($photo, $holdingTenantId);
            }

            $designationId = isset($data['designation_id']) ? (int) $data['designation_id'] : null;
            $designationLabel = null;
            if ($designationId) {
                $designationLabel = \App\Models\Designation::query()->whereKey($designationId)->value('label');
            }

            $teacher = Teacher::create([
                'tenant_id'         => $holdingTenantId,
                'name'              => $name,
                'email'             => $email,
                'mobile'            => $mobile,
                'dob'               => $data['dob'] ?? null,
                'gender'            => $data['gender'] ?? null,
                'teaching_type_id'  => isset($data['teaching_type_id']) ? (int) $data['teaching_type_id'] : null,
                'designation_id'    => $designationId,
                'designation'       => $designationLabel,
                'experience_years'  => isset($data['experience']) ? (int) $data['experience'] : null,
                'photo'             => $photoPath,
                'status'            => 'active',
                'verified_at'       => null,
            ]);
            $teacherCreated = true;
        }

        $this->eligibility->assertTeacherEligible($program, $teacher);

        $seat = app(TrainingWaitlistService::class)->resolveCreateAttributes($program, 'qr');

        $registration = DB::transaction(function () use ($program, $teacher, $school, $pendingSchool, $data, $teacherCreated, $seat) {
            return TrainingRegistration::create(array_merge([
                'program_id'          => $program->id,
                'teacher_id'          => $teacher->id,
                'school_id'           => $school?->id ?? $program->tenant_id,
                'registration_source' => 'qr',
                'consent_at'          => now(),
                'department'          => $data['department'] ?? null,
                'teacher_created'     => $teacherCreated,
                'pending_school_id'   => $pendingSchool?->id,
                'fee_status'          => $program->hasFee() && $seat['status'] !== 'waitlisted' ? 'auto_approved' : null,
            ], $seat));
        });

        if ($school && $program->usesSchoolBatchFee() && $registration->status !== 'waitlisted') {
            app(TrainingSchoolFeeService::class)->syncForSchool($program, $school);
        }

        $this->audit->training(
            $program,
            'training.qr.registered',
            "QR registration: {$teacher->name}",
            [
                'registration_id' => $registration->id,
                'teacher_id'      => $teacher->id,
                'teacher_created' => $teacherCreated,
                'school_id'       => $school?->id,
                'pending_school'  => $pendingSchool?->id,
            ],
            $registration,
        );

        if ($teacherCreated) {
            $this->audit->training(
                $program,
                'training.qr.teacher_created',
                "Teacher created via QR (pending school verification): {$teacher->name}",
                ['teacher_id' => $teacher->id, 'school_id' => $school?->id],
                $teacher,
            );
        }

        if ($pendingSchool) {
            $this->audit->training(
                $program,
                'training.qr.pending_school',
                "Pending school request: {$pendingSchool->school_name}",
                ['pending_school_id' => $pendingSchool->id],
                $pendingSchool,
            );
        }

        return [
            'registration'   => $registration,
            'teacher'        => $teacher,
            'teacher_created'=> $teacherCreated,
            'pending_school' => $pendingSchool,
        ];
    }

    /**
     * @return list<array{id: string, name: string, school_code: ?string, affiliation: ?string, membership_status: ?string, label: string}>
     */
    public function listSchools(TrainingProgram $program, int $limit = 500): array
    {
        return Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $program->tenant_id)
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->where('membership_status', 'approved')
                    ->orWhereNull('membership_status');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'school_prefix', 'membership_status', 'application_payload'])
            ->map(fn (Tenant $s) => $this->schoolOption($s))
            ->all();
    }

    /**
     * @return list<array{id: string, name: string, school_code: ?string, affiliation: ?string, membership_status: ?string, label: string}>
     */
    public function searchSchools(TrainingProgram $program, string $query, int $limit = 20): array
    {
        $q = trim($query);
        if ($q === '') {
            return $this->listSchools($program, $limit);
        }

        $schools = Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $program->tenant_id)
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->where('membership_status', 'approved')
                    ->orWhereNull('membership_status');
            })
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'school_prefix', 'membership_status', 'application_payload'])
            ->map(fn (Tenant $s) => $this->schoolOption($s));

        $needle = mb_strtolower($q);

        return $schools
            ->filter(function (array $school) use ($needle) {
                return str_contains(mb_strtolower($school['name']), $needle)
                    || str_contains(mb_strtolower((string) $school['affiliation']), $needle)
                    || str_contains(mb_strtolower((string) $school['school_code']), $needle);
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return array{id: string, name: string, school_code: ?string, affiliation: ?string, membership_status: ?string, label: string} */
    private function schoolOption(Tenant $school): array
    {
        $affiliation = SchoolApplicationForm::schoolAffiliation($school);
        $schoolCode = filled($school->school_prefix) ? (string) $school->school_prefix : null;
        $bracketParts = array_values(array_filter([$affiliation, $schoolCode]));
        $label = $bracketParts
            ? $school->name.' ('.implode(' · ', $bracketParts).')'
            : $school->name;

        return [
            'id'                => $school->id,
            'name'              => $school->name,
            'school_code'       => $schoolCode,
            'affiliation'       => $affiliation,
            'membership_status' => $school->membership_status,
            'label'             => $label,
        ];
    }

    private function findExistingTeacher(
        TrainingProgram $program,
        ?Tenant $school,
        ?string $email,
        ?string $mobile,
        string $name,
    ): ?Teacher {
        $schoolIds = Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $program->tenant_id)
            ->pluck('id')
            ->all();

        $schoolIds[] = $program->tenant_id;

        $base = Teacher::query()->whereIn('tenant_id', $schoolIds);

        if ($email) {
            $byEmail = (clone $base)->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        if ($mobile && strlen($mobile) >= 8) {
            $byMobile = (clone $base)->where('mobile', $mobile)->first();
            if ($byMobile) {
                return $byMobile;
            }
        }

        if ($school) {
            $byNameSchool = Teacher::query()
                ->where('tenant_id', $school->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();
            if ($byNameSchool) {
                return $byNameSchool;
            }

            // Fuzzy name match within the same school only (email/mobile already missed).
            // Threshold: similar_text percent >= 90, OR levenshtein distance <= 2 when
            // both names are short (≤ 12 chars after normalize). Conservative to avoid
            // false merges across distinct teachers.
            $normalizedNeedle = $this->normalizeTeacherName($name);
            if ($normalizedNeedle !== '') {
                $candidates = Teacher::query()
                    ->where('tenant_id', $school->id)
                    ->get(['id', 'name']);

                foreach ($candidates as $candidate) {
                    $normalizedCandidate = $this->normalizeTeacherName((string) $candidate->name);
                    if ($normalizedCandidate === '') {
                        continue;
                    }

                    similar_text($normalizedNeedle, $normalizedCandidate, $percent);
                    if ($percent >= 90.0) {
                        return Teacher::query()->find($candidate->id);
                    }

                    $maxLen = max(strlen($normalizedNeedle), strlen($normalizedCandidate));
                    if ($maxLen <= 12 && levenshtein($normalizedNeedle, $normalizedCandidate) <= 2) {
                        return Teacher::query()->find($candidate->id);
                    }
                }
            }
        }

        return null;
    }

    private function normalizeTeacherName(string $name): string
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));

        return $name;
    }

    /**
     * Refresh profile fields on a matched existing teacher from QR form data.
     *
     * @param  array<string, mixed>  $data
     */
    private function refreshExistingTeacherProfile(Teacher $teacher, array $data, ?UploadedFile $photo = null): void
    {
        $updates = [];

        if (! empty($data['dob'])) {
            $updates['dob'] = $data['dob'];
        }
        if (! empty($data['gender'])) {
            $updates['gender'] = $data['gender'];
        }
        if (isset($data['teaching_type_id']) && $data['teaching_type_id'] !== '' && $data['teaching_type_id'] !== null) {
            $updates['teaching_type_id'] = (int) $data['teaching_type_id'];
        }
        if (isset($data['designation_id']) && $data['designation_id'] !== '' && $data['designation_id'] !== null) {
            $designationId = (int) $data['designation_id'];
            $updates['designation_id'] = $designationId;
            $label = \App\Models\Designation::query()->whereKey($designationId)->value('label');
            if ($label) {
                $updates['designation'] = $label;
            }
        }
        if (isset($data['experience']) && $data['experience'] !== '' && $data['experience'] !== null) {
            $updates['experience_years'] = (int) $data['experience'];
        }
        if ($photo) {
            $updates['photo'] = TenantStorage::storeTeacherPhoto($photo, $teacher->tenant_id);
        }

        if ($updates === []) {
            return;
        }

        $material = array_intersect_key($updates, array_flip([
            'dob', 'gender', 'teaching_type_id', 'designation_id', 'designation', 'experience_years', 'photo',
        ]));

        if ($material !== [] && $teacher->verified_at !== null) {
            $updates['verified_at'] = null;
            $updates['verified_by_user_id'] = null;
        }

        $teacher->update($updates);
    }
}

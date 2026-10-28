<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\AcademicYear;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * School-owned model exams: conductor_level=school, no Talent Search fees/hall tickets.
 * Exams live in the parent Sahodaya DB (tenant_id) and are scoped by school_id.
 */
class McqSchoolModelExamService
{
    public function assertOwnedBySchool(McqExam $exam, Tenant $school): void
    {
        abort_unless(
            $exam->isSchoolModelExam()
                && $exam->tenant_id === $school->parent_id
                && $exam->belongsToSchool($school->id),
            404,
        );
    }

    public function assertBankOwnedBySchool(McqQuestionBank $bank, Tenant $school): void
    {
        abort_unless(
            $bank->sahodaya_id === $school->parent_id
                && $bank->school_id === $school->id,
            404,
        );
    }

    /** @param  array<string, mixed>  $data */
    public function create(Tenant $school, array $data, ?int $createdByUserId = null): McqExam
    {
        abort_unless($school->type === 'school' && filled($school->parent_id), 422, 'Model exams require a school under a Sahodaya.');

        $payload = McqExamPayload::applyDefaults([
            ...$data,
            'tenant_id'        => $school->parent_id,
            'school_id'        => $school->id,
            'conductor_level'  => 'school',
            'status'           => 'draft',
            'exam_type'        => $data['exam_type'] ?? 'assessment',
            'delivery_mode'    => $data['delivery_mode'] ?? 'online',
            'fee_type'         => 'none',
            'fee_amount'       => null,
            'school_discount_amount' => null,
            'academic_year_id' => AcademicYear::activeId(),
            'next_hall_ticket_no' => 100,
            'settings_json'    => array_merge($data['settings_json'] ?? [], [
                'requires_hall_ticket'       => false,
                'require_verified_students'  => false,
                'multi_marking'              => $data['multi_marking'] ?? 'partial',
                'shuffle_questions'          => (bool) ($data['shuffle_questions'] ?? true),
                'shuffle_options'            => (bool) ($data['shuffle_options'] ?? true),
                'product_mode'               => 'school_model_exam',
            ]),
        ]);

        if ($error = McqExamPayload::eligibilityError($payload)) {
            abort(422, $error);
        }

        return McqExam::create($payload);
    }

    /**
     * Enroll eligible active students without fee/hall-ticket ceremony.
     * Allowed in draft so teachers can prep the roster before publish.
     *
     * @return array{created: int, skipped: int, total_eligible: int}
     */
    public function enrollEligible(McqExam $exam, Tenant $school): array
    {
        $this->assertOwnedBySchool($exam, $school);

        $students = Student::where('tenant_id', $school->id)->active()
            ->with('schoolClass:id,name,class_category_id')
            ->orderBy('id')
            ->get();

        $eligible = app(McqEligibilityService::class)->eligibleStudents($exam, $students);
        $approval = app(McqRegistrationApprovalService::class)->initialApprovalStatus($exam);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($exam, $school, $eligible, $approval, &$created, &$skipped) {
            foreach ($eligible as $student) {
                $existing = McqRegistration::where('exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing && ! $existing->isCancelled()) {
                    $skipped++;
                    continue;
                }

                if ($existing) {
                    $existing->update([
                        'school_id'            => $school->id,
                        'status'               => 'registered',
                        'approval_status'      => $approval,
                        'cancelled_at'         => null,
                        'cancelled_by_user_id' => null,
                    ]);
                    $created++;
                    continue;
                }

                McqRegistration::create([
                    'exam_id'         => $exam->id,
                    'student_id'      => $student->id,
                    'school_id'       => $school->id,
                    'status'          => 'registered',
                    'approval_status' => $approval,
                ]);
                $created++;
            }
        });

        return [
            'created'        => $created,
            'skipped'        => $skipped,
            'total_eligible' => $eligible->count(),
        ];
    }

    /** @return Collection<int, Student> */
    public function eligibleStudents(McqExam $exam, Tenant $school): Collection
    {
        $this->assertOwnedBySchool($exam, $school);

        $students = Student::where('tenant_id', $school->id)->active()
            ->with('schoolClass:id,name,class_category_id')
            ->orderBy('name')
            ->get();

        return app(McqEligibilityService::class)->eligibleStudents($exam, $students);
    }

    public function publish(McqExam $exam, Tenant $school, bool $autoEnroll = true): McqExam
    {
        $this->assertOwnedBySchool($exam, $school);

        if ($exam->questionBanks()->where('status', 'active')->doesntExist()
            && empty($exam->settings_json['paper_assignments'] ?? [])) {
            abort(422, 'Attach at least one Ready question bank before publishing.');
        }

        $exam->update(['status' => 'published']);

        if ($autoEnroll) {
            $this->enrollEligible($exam->fresh(), $school);
        }

        return $exam->fresh();
    }

    public function schoolBanksQuery(Tenant $school)
    {
        return McqQuestionBank::where('sahodaya_id', $school->parent_id)
            ->where('school_id', $school->id);
    }
}

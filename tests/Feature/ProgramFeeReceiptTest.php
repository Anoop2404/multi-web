<?php

namespace Tests\Feature;

use App\Models\FeeReceipt;
use App\Models\McqExam;
use App\Models\McqSchoolFee;
use App\Models\SahodayaProfile;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Fees\ProgramFeeReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProgramFeeReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcq_batch_approval_issues_numbered_receipt(): void
    {
        [$sahodaya, $school] = $this->seedTenants();

        $exam = McqExam::create([
            'tenant_id'  => $sahodaya->id,
            'title'      => 'Science MCQ',
            'exam_type'  => 'assessment',
            'status'     => 'published',
            'fee_type'   => 'per_student',
            'fee_amount' => 100,
        ]);

        $schoolFee = McqSchoolFee::create([
            'exam_id'       => $exam->id,
            'school_id'     => $school->id,
            'student_count' => 5,
            'total_due'     => 500,
            'status'        => 'proof_uploaded',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => McqSchoolFee::class,
            'feeable_id'   => $schoolFee->id,
            'file_path'    => 'mcq-payments/test/proof.png',
            'amount'       => 500,
            'status'       => 'approved',
            'payment_date' => now()->toDateString(),
        ]);

        $schoolFee->update(['fee_receipt_id' => $receipt->id]);

        $issued = app(ProgramFeeReceiptService::class)->issueMcqSchoolBatch($schoolFee->fresh(['exam', 'school']), $receipt);

        $this->assertNotNull($issued->receipt_number);
        $this->assertStringStartsWith('MCQ-', $issued->receipt_number);
        $this->assertNotNull($issued->generated_receipt_path);
        $this->assertStringContainsString('Science MCQ', app(ProgramFeeReceiptService::class)->readGeneratedReceipt($issued));
    }

    public function test_training_approval_issues_numbered_receipt(): void
    {
        [$sahodaya, $school] = $this->seedTenants();

        $program = TrainingProgram::create([
            'tenant_id'  => $sahodaya->id,
            'title'      => 'Leadership Workshop',
            'fee_amount' => 750,
            'status'     => 'published',
        ]);

        $teacher = Teacher::create([
            'tenant_id' => $school->id,
            'name'      => 'Jane Teacher',
            'status'    => 'active',
        ]);

        $registration = TrainingRegistration::create([
            'program_id' => $program->id,
            'teacher_id' => $teacher->id,
            'school_id'  => $school->id,
            'status'     => 'registered',
        ]);

        $receipt = FeeReceipt::create([
            'feeable_type' => TrainingRegistration::class,
            'feeable_id'   => $registration->id,
            'file_path'    => 'training-payments/test/proof.png',
            'amount'       => 750,
            'status'       => 'approved',
            'payment_date' => now()->toDateString(),
        ]);

        $registration->update(['fee_receipt_id' => $receipt->id]);

        $issued = app(ProgramFeeReceiptService::class)->issueTraining($registration->fresh(['program', 'teacher', 'school']), $receipt);

        $this->assertNotNull($issued->receipt_number);
        $this->assertStringStartsWith('TRN-', $issued->receipt_number);
        $this->assertStringContainsString('Leadership Workshop', app(ProgramFeeReceiptService::class)->readGeneratedReceipt($issued));
    }

    /** @return array{0: Tenant, 1: Tenant} */
    private function seedTenants(): array
    {
        $sahodayaId = (string) Str::uuid();
        $schoolId = (string) Str::uuid();

        $sahodaya = Tenant::create([
            'id'        => $sahodayaId,
            'name'      => 'Test Sahodaya',
            'type'      => 'sahodaya',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id'        => $schoolId,
            'name'      => 'Test School',
            'type'      => 'school',
            'parent_id' => $sahodayaId,
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'           => $sahodayaId,
            'receipt_next_number' => 1,
        ]);

        return [$sahodaya, $school];
    }
}

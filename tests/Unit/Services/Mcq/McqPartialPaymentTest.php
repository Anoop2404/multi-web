<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\FeeReceipt;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Mcq\McqSchoolFeeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqPartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeExamWithTwoRegistrations(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Sahodaya', 'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id, 'prefix' => 'TS',
            'student_data_mode' => 'counts_only', 'receipt_next_number' => 1,
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School',
            'parent_id' => $sahodaya->id, 'is_active' => true, 'membership_status' => 'approved',
        ]);

        $exam = McqExam::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Talent Search', 'status' => 'published',
            'fee_type' => 'flat', 'fee_amount' => 150, 'next_hall_ticket_no' => 1001,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 8', 'is_active' => true]);

        foreach (range(1, 2) as $i) {
            $student = Student::create([
                'tenant_id' => $school->id, 'school_class_id' => $class->id,
                'name' => 'Student '.$i, 'status' => 'active',
            ]);
            McqRegistration::create([
                'exam_id' => $exam->id, 'student_id' => $student->id,
                'school_id' => $school->id, 'status' => 'registered',
                'approval_status' => 'pending_payment',
            ]);
        }

        return [$exam, $school];
    }

    public function test_partial_payment_leaves_balance_and_withholds_hall_tickets(): void
    {
        [$exam, $school] = $this->makeExamWithTwoRegistrations();
        $service = app(McqSchoolFeeService::class);

        $fee = $service->syncForSchool($exam, $school);
        $this->assertEquals(300.0, (float) $fee->total_due);

        // School pays ₹100 of ₹300.
        $receipt = FeeReceipt::create([
            'feeable_type' => McqSchoolFee::class, 'feeable_id' => $fee->id,
            'file_path' => 'proofs/p1.pdf',
            'amount' => 100, 'status' => 'uploaded', 'payment_date' => now()->toDateString(),
        ]);
        $fee->update(['fee_receipt_id' => $receipt->id, 'status' => 'proof_uploaded']);

        $service->approve($fee, 1);
        $fee->refresh();

        $this->assertSame('partial', $fee->status);
        $this->assertEquals(100.0, (float) $fee->amount_paid);
        $this->assertEquals(200.0, $fee->outstandingBalance());
        $this->assertFalse($fee->isFullyPaid());

        // No hall tickets yet while a balance remains.
        $this->assertSame(0, McqRegistration::where('exam_id', $exam->id)
            ->where('approval_status', 'approved')->count());

        // Pay the remaining ₹200.
        $receipt2 = FeeReceipt::create([
            'feeable_type' => McqSchoolFee::class, 'feeable_id' => $fee->id,
            'file_path' => 'proofs/p2.pdf',
            'amount' => 200, 'status' => 'uploaded', 'payment_date' => now()->toDateString(),
        ]);
        $fee->update(['fee_receipt_id' => $receipt2->id, 'status' => 'proof_uploaded']);

        $service->approve($fee->fresh(), 1);
        $fee->refresh();

        $this->assertSame('approved', $fee->status);
        $this->assertEquals(300.0, (float) $fee->amount_paid);
        $this->assertTrue($fee->isFullyPaid());
        $this->assertSame(2, McqRegistration::where('exam_id', $exam->id)
            ->where('approval_status', 'approved')->count());
    }
}

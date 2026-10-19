<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\FeeReceipt;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Mcq\McqHallTicketService;
use App\Services\Mcq\McqRegistrationApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqRegistrationApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;
    private Tenant $schoolPaid;
    private Tenant $schoolUnpaid;
    private McqExam $paidExam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Sahodaya',
            'is_active' => true,
        ]);

        $this->schoolPaid = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Paid School',
            'parent_id' => $this->sahodaya->id,
            'is_active' => true,
        ]);

        $this->schoolUnpaid = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Unpaid School',
            'parent_id' => $this->sahodaya->id,
            'is_active' => true,
        ]);

        $this->paidExam = McqExam::create([
            'tenant_id'  => $this->sahodaya->id,
            'title'      => 'Paid Talent Search',
            'status'     => 'published',
            'fee_type'   => 'flat',
            'fee_amount' => 100,
        ]);
    }

    public function test_sync_reverts_unpaid_registrations_and_confirms_paid_registrations(): void
    {
        $classPaid = SchoolClass::create(['tenant_id' => $this->schoolPaid->id, 'name' => 'Class 5', 'is_active' => true]);
        $studentPaid = Student::create([
            'tenant_id'       => $this->schoolPaid->id,
            'school_class_id' => $classPaid->id,
            'name'            => 'Student Paid',
            'status'          => 'active',
        ]);

        $regPaid = McqRegistration::create([
            'exam_id'         => $this->paidExam->id,
            'student_id'      => $studentPaid->id,
            'school_id'       => $this->schoolPaid->id,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
        ]);

        $classUnpaid = SchoolClass::create(['tenant_id' => $this->schoolUnpaid->id, 'name' => 'Class 5', 'is_active' => true]);
        $studentUnpaid = Student::create([
            'tenant_id'       => $this->schoolUnpaid->id,
            'school_class_id' => $classUnpaid->id,
            'name'            => 'Student Unpaid',
            'status'          => 'active',
        ]);

        // Falsely marked approved (e.g. from previous renumbering bug)
        $regUnpaid = McqRegistration::create([
            'exam_id'         => $this->paidExam->id,
            'student_id'      => $studentUnpaid->id,
            'school_id'       => $this->schoolUnpaid->id,
            'status'          => 'registered',
            'approval_status' => 'approved',
        ]);

        // Mark paid school fee as approved
        McqSchoolFee::create([
            'tenant_id'     => $this->sahodaya->id,
            'exam_id'       => $this->paidExam->id,
            'school_id'     => $this->schoolPaid->id,
            'student_count' => 1,
            'total_due'     => 100,
            'amount_paid'   => 100,
            'status'        => 'approved',
        ]);

        // Unpaid school fee is pending
        McqSchoolFee::create([
            'tenant_id'     => $this->sahodaya->id,
            'exam_id'       => $this->paidExam->id,
            'school_id'     => $this->schoolUnpaid->id,
            'student_count' => 1,
            'total_due'     => 100,
            'amount_paid'   => 0,
            'status'        => 'pending',
        ]);

        $service = app(McqRegistrationApprovalService::class);
        $result = $service->syncExamApprovalStatuses($this->paidExam);

        $this->assertEquals(1, $result['approved']);
        $this->assertEquals(1, $result['pending_payment']);

        $this->assertEquals('approved', $regPaid->fresh()->approval_status);
        $this->assertEquals('pending_payment', $regUnpaid->fresh()->approval_status);
    }

    public function test_sync_approves_all_active_registrations_for_free_exam(): void
    {
        $freeExam = McqExam::create([
            'tenant_id'  => $this->sahodaya->id,
            'title'      => 'Free Practice Exam',
            'status'     => 'published',
            'fee_type'   => 'none',
            'fee_amount' => 0,
        ]);

        $class = SchoolClass::create(['tenant_id' => $this->schoolUnpaid->id, 'name' => 'Class 6', 'is_active' => true]);
        $student = Student::create([
            'tenant_id'       => $this->schoolUnpaid->id,
            'school_class_id' => $class->id,
            'name'            => 'Student Free',
            'status'          => 'active',
        ]);

        $reg = McqRegistration::create([
            'exam_id'         => $freeExam->id,
            'student_id'      => $student->id,
            'school_id'       => $this->schoolUnpaid->id,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
        ]);

        $service = app(McqRegistrationApprovalService::class);
        $result = $service->syncExamApprovalStatuses($freeExam);

        $this->assertEquals(1, $result['approved']);
        $this->assertEquals(0, $result['pending_payment']);
        $this->assertEquals('approved', $reg->fresh()->approval_status);
    }

    public function test_sync_reverts_unpaid_candidates_even_if_renumbered(): void
    {
        $class = SchoolClass::create(['tenant_id' => $this->schoolUnpaid->id, 'name' => 'Class 7', 'is_active' => true]);
        $student = Student::create([
            'tenant_id'       => $this->schoolUnpaid->id,
            'school_class_id' => $class->id,
            'name'            => 'Candidate 1',
            'status'          => 'active',
        ]);

        $reg = McqRegistration::create([
            'exam_id'         => $this->paidExam->id,
            'student_id'      => $student->id,
            'school_id'       => $this->schoolUnpaid->id,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
        ]);

        McqSchoolFee::create([
            'tenant_id'     => $this->sahodaya->id,
            'exam_id'       => $this->paidExam->id,
            'school_id'     => $this->schoolUnpaid->id,
            'student_count' => 1,
            'total_due'     => 100,
            'amount_paid'   => 0,
            'status'        => 'pending',
        ]);

        $ticketService = app(McqHallTicketService::class);
        $ticketService->renumberByClass($this->paidExam);

        $reg->refresh();
        $this->assertNotNull($reg->hall_ticket_no);

        // When sync runs, since school fee is pending and unpaid, it is corrected back to pending_payment
        app(McqRegistrationApprovalService::class)->syncExamApprovalStatuses($this->paidExam);
        $this->assertEquals('pending_payment', $reg->fresh()->approval_status);
    }

    public function test_sync_approves_exact_student_count_for_partially_paid_school(): void
    {
        // Fee is 100 per student. School registers 5 students (total due 500).
        // School paid 300 (covers 3 students, 2 students due).
        $class = SchoolClass::create(['tenant_id' => $this->schoolUnpaid->id, 'name' => 'Class 8', 'is_active' => true]);

        $registrations = [];
        for ($i = 1; $i <= 5; $i++) {
            $student = Student::create([
                'tenant_id'       => $this->schoolUnpaid->id,
                'school_class_id' => $class->id,
                'name'            => "Student {$i}",
                'status'          => 'active',
            ]);

            // Assume all were falsely approved previously e.g. via renumber
            $registrations[] = McqRegistration::create([
                'exam_id'         => $this->paidExam->id,
                'student_id'      => $student->id,
                'school_id'       => $this->schoolUnpaid->id,
                'status'          => 'registered',
                'approval_status' => 'approved',
            ]);
        }

        McqSchoolFee::create([
            'tenant_id'     => $this->sahodaya->id,
            'exam_id'       => $this->paidExam->id,
            'school_id'     => $this->schoolUnpaid->id,
            'student_count' => 5,
            'total_due'     => 500,
            'amount_paid'   => 300,
            'status'        => 'partial',
        ]);

        app(McqRegistrationApprovalService::class)->syncExamApprovalStatuses($this->paidExam);

        // First 3 should be approved, remaining 2 should be pending_payment
        $this->assertEquals('approved', $registrations[0]->fresh()->approval_status);
        $this->assertEquals('approved', $registrations[1]->fresh()->approval_status);
        $this->assertEquals('approved', $registrations[2]->fresh()->approval_status);
        $this->assertEquals('pending_payment', $registrations[3]->fresh()->approval_status);
        $this->assertEquals('pending_payment', $registrations[4]->fresh()->approval_status);
    }
}

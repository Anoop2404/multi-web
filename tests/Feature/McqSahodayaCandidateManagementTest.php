<?php

namespace Tests\Feature;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McqSahodayaCandidateManagementTest extends TestCase
{
    use RefreshDatabase;

    private function setUpEnvironment(array $examAttributes = []): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Sahodaya Hub',
            'domain'    => 'sahodaya-hub.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'                    => $sahodaya->id,
            'prefix'                       => 'SH',
            'student_data_mode'            => 'counts_only',
            'require_student_verification' => false,
        ]);

        $sahodayaAdmin = User::factory()->create([
            'tenant_id'         => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $school = Tenant::create([
            'id'                => (string) Str::uuid(),
            'type'              => 'school',
            'name'              => 'Greenfield Academy',
            'parent_id'         => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name'      => 'Class 10',
            'is_active' => true,
        ]);

        $student1 = Student::create([
            'tenant_id'        => $school->id,
            'school_class_id'  => $class->id,
            'name'             => 'Alice Candidate',
            'admission_number' => 'ADM-001',
            'status'           => 'active',
        ]);

        $student2 = Student::create([
            'tenant_id'        => $school->id,
            'school_class_id'  => $class->id,
            'name'             => 'Bob Candidate',
            'admission_number' => 'ADM-002',
            'status'           => 'active',
        ]);

        $exam = McqExam::create(array_merge([
            'tenant_id'              => $sahodaya->id,
            'title'                  => 'Talent Search 2026',
            'exam_type'              => 'assessment',
            'status'                 => 'published',
            'delivery_mode'          => 'offline',
            'fee_type'               => 'flat',
            'fee_amount'             => 100,
            'next_hall_ticket_no'    => 100,
            'registration_opens_at'  => now()->subDays(10),
            'registration_closes_at' => now()->subDay(), // Window already closed
        ], $examAttributes));

        return compact('sahodaya', 'sahodayaAdmin', 'school', 'class', 'student1', 'student2', 'exam');
    }

    public function test_sahodaya_can_fetch_eligible_candidates_with_window_closed(): void
    {
        $env = $this->setUpEnvironment();

        $response = $this->actingAs($env['sahodayaAdmin'])
            ->getJson("/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/eligible-candidates?school_id={$env['school']->id}&search=Alice");

        $response->assertOk();
        $response->assertJsonStructure([
            'candidates' => [
                '*' => ['id', 'name', 'admission_number', 'class_name', 'is_eligible', 'is_registered', 'was_cancelled'],
            ],
            'classes',
            'tickets_already_issued',
            'allows_students',
            'window_active',
        ]);

        $candidates = $response->json('candidates');
        $this->assertCount(1, $candidates);
        $this->assertSame('Alice Candidate', $candidates[0]['name']);
        $this->assertSame('Class 10', $candidates[0]['class_name']);
        $this->assertTrue($candidates[0]['is_eligible']);
        $this->assertFalse($candidates[0]['is_registered']);
        $this->assertFalse($response->json('window_active'));
    }

    public function test_sahodaya_can_add_student_after_registration_window_closed_and_hall_tickets_issued(): void
    {
        $env = $this->setUpEnvironment(['delivery_mode' => 'online']);

        // Register Student 1 first with an issued hall ticket
        $reg1 = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student1']->id,
            'status'          => 'registered',
            'approval_status' => 'approved',
            'hall_ticket_no'  => '100',
        ]);

        // Attempting to register via school admin would fail due to window closed
        $this->assertFalse($env['exam']->registrationWindowActive());

        // Sahodaya Admin overrides and adds Student 2 with issue_hall_ticket=true
        $response = $this->actingAs($env['sahodayaAdmin'])
            ->post("/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/registrations", [
                'school_id'         => $env['school']->id,
                'candidate_type'    => 'student',
                'student_id'        => $env['student2']->id,
                'issue_hall_ticket' => true,
                'reason'            => 'Special permission granted after deadline',
            ]);

        $response->assertSessionHas('success');

        $reg2 = McqRegistration::where('exam_id', $env['exam']->id)
            ->where('student_id', $env['student2']->id)
            ->first();

        $this->assertNotNull($reg2);
        $this->assertSame('registered', $reg2->status);
        $this->assertSame('approved', $reg2->approval_status);
        $this->assertNotNull($reg2->approved_at);
        $this->assertSame($env['sahodayaAdmin']->id, $reg2->approved_by_user_id);

        // Next sequential roll number for Class 10 is 101
        $this->assertSame('101', $reg2->hall_ticket_no);

        // Online passcode was created
        $this->assertNotNull($reg2->exam_passcode);

        // School fee was synced
        $fee = \App\Models\McqSchoolFee::where('exam_id', $env['exam']->id)
            ->where('school_id', $env['school']->id)
            ->first();
        $this->assertNotNull($fee);
        $this->assertSame(2, $fee->student_count);
        $this->assertEquals(200.0, (float) $fee->total_due);
    }

    public function test_sahodaya_can_remove_student_after_hall_ticket_issued(): void
    {
        $env = $this->setUpEnvironment();

        // Create student registration with hall ticket and seat
        $reg = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student1']->id,
            'status'          => 'registered',
            'approval_status' => 'approved',
            'hall_ticket_no'  => '100',
            'hall_room'       => 'Hall A',
            'seat_no'         => '1',
        ]);

        // Sync initial fee
        app(\App\Services\Mcq\McqSchoolFeeService::class)->syncForSchool($env['exam'], $env['school']);

        // Remove student
        $response = $this->actingAs($env['sahodayaAdmin'])
            ->post("/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/registrations/{$reg->id}/cancel", [
                'reason' => 'Student transferred to another region',
            ]);

        $response->assertSessionHas('success');

        $fresh = $reg->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame($env['sahodayaAdmin']->id, $fresh->cancelled_by_user_id);
        $this->assertNull($fresh->hall_ticket_no);
        $this->assertNull($fresh->seat_no);
        $this->assertNull($fresh->hall_room);

        // School fee reflects 0 students
        $fee = \App\Models\McqSchoolFee::where('exam_id', $env['exam']->id)
            ->where('school_id', $env['school']->id)
            ->first();
        $this->assertSame(0, $fee->student_count);
        $this->assertEquals(0.0, (float) $fee->total_due);
    }

    public function test_sahodaya_can_re_add_previously_cancelled_student(): void
    {
        $env = $this->setUpEnvironment();

        $reg = McqRegistration::create([
            'exam_id'              => $env['exam']->id,
            'school_id'            => $env['school']->id,
            'student_id'           => $env['student1']->id,
            'status'               => 'cancelled',
            'approval_status'      => 'approved',
            'cancelled_at'         => now()->subDay(),
            'cancelled_by_user_id' => $env['sahodayaAdmin']->id,
        ]);

        $response = $this->actingAs($env['sahodayaAdmin'])
            ->post("/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/registrations", [
                'school_id'         => $env['school']->id,
                'candidate_type'    => 'student',
                'student_id'        => $env['student1']->id,
                'issue_hall_ticket' => true,
                'reason'            => 'Re-instated by Sahodaya',
            ]);

        $response->assertSessionHas('success');

        // Verify the existing row was reused rather than creating a duplicate row
        $registrations = McqRegistration::where('exam_id', $env['exam']->id)
            ->where('student_id', $env['student1']->id)
            ->get();

        $this->assertCount(1, $registrations);

        $readded = $registrations->first();
        $this->assertSame($reg->id, $readded->id);
        $this->assertSame('registered', $readded->status);
        $this->assertNull($readded->cancelled_at);
        $this->assertNull($readded->cancelled_by_user_id);
        $this->assertSame('100', $readded->hall_ticket_no);
    }

    public function test_sahodaya_can_assign_missing_reg_nos_to_unapproved_school_registrations(): void
    {
        $env = $this->setUpEnvironment();

        // Create a registration as if submitted by school with pending approval and no hall ticket
        $reg = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student1']->id,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
            'hall_ticket_no'  => null,
        ]);

        $response = $this->actingAs($env['sahodayaAdmin'])
            ->post("/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/hall-tickets/generate");

        $response->assertSessionHas('success');

        $reg->refresh();
        $this->assertSame('approved', $reg->approval_status);
        $this->assertSame('100', $reg->hall_ticket_no);
    }

    public function test_sahodaya_can_assign_single_reg_no(): void
    {
        $env = $this->setUpEnvironment();

        $reg = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student1']->id,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
            'hall_ticket_no'  => null,
        ]);

        $response = $this->actingAs($env['sahodayaAdmin'])
            ->post("/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/registrations/{$reg->id}/issue-reg-no");

        $response->assertSessionHas('success');

        $reg->refresh();
        $this->assertSame('approved', $reg->approval_status);
        $this->assertSame('100', $reg->hall_ticket_no);
    }

    public function test_sahodaya_can_regenerate_all_reg_nos_including_unassigned_even_when_published(): void
    {
        $env = $this->setUpEnvironment();

        // Mark exam as published
        $env['exam']->update(['hall_tickets_published' => true]);

        // Student 1 has existing number
        $reg1 = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student1']->id,
            'status'          => 'registered',
            'approval_status' => 'approved',
            'hall_ticket_no'  => '999',
        ]);

        // Student 2 is newly added from school, no number yet
        $reg2 = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student2']->id,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
            'hall_ticket_no'  => null,
        ]);

        $response = $this->actingAs($env['sahodayaAdmin'])
            ->post("/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/hall-tickets/renumber", [
                'force' => true,
            ]);

        $response->assertSessionHas('success');

        $reg1->refresh();
        $reg2->refresh();

        $this->assertSame('100', $reg1->hall_ticket_no);
        $this->assertSame('101', $reg2->hall_ticket_no);
        $this->assertSame('approved', $reg2->approval_status);
    }

    public function test_replacement_student_auto_approved_and_tallies_when_school_fee_is_fully_paid(): void
    {
        $env = $this->setUpEnvironment(['fee_amount' => 150]);

        // School registers student 1 and pays in full
        $reg1 = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student1']->id,
            'status'          => 'registered',
            'approval_status' => 'approved',
            'hall_ticket_no'  => '100',
        ]);

        $receipt = \App\Models\FeeReceipt::create([
            'feeable_type' => \App\Models\McqSchoolFee::class,
            'feeable_id'   => 0, // will re-point after fee creation
            'tenant_id'    => $env['sahodaya']->id,
            'amount'       => 150.00,
            'status'       => 'approved',
            'file_path'    => 'proofs/dummy.pdf',
        ]);

        $fee = \App\Models\McqSchoolFee::create([
            'exam_id'        => $env['exam']->id,
            'school_id'      => $env['school']->id,
            'student_count'  => 1,
            'total_due'      => 150.00,
            'amount_paid'    => 150.00,
            'status'         => 'approved',
            'fee_receipt_id' => $receipt->id,
        ]);
        $receipt->update(['feeable_id' => $fee->id]);

        // Cancel student 1
        $reg1->update(['status' => 'cancelled']);
        app(\App\Services\Mcq\McqSchoolFeeService::class)->syncForSchool($env['exam'], $env['school'], 'Cancelled student 1');

        $fee->refresh();
        $this->assertSame(0, $fee->student_count);
        $this->assertSame(0.0, (float) $fee->total_due);

        // Add replacement student 2
        $reg2 = McqRegistration::create([
            'exam_id'         => $env['exam']->id,
            'school_id'       => $env['school']->id,
            'student_id'      => $env['student2']->id,
            'status'          => 'registered',
            'approval_status' => 'pending_payment',
        ]);

        // Sync runs when student 2 is added
        app(\App\Services\Mcq\McqSchoolFeeService::class)->syncForSchool($env['exam'], $env['school']);

        // Since payment (150) covers total_due (150), student 2 must be auto-approved and get a hall ticket
        $reg2->refresh();
        $this->assertSame('approved', $reg2->approval_status);
        $this->assertNotNull($reg2->hall_ticket_no);
        $this->assertSame('101', $reg2->hall_ticket_no);

        $fee->refresh();
        $this->assertSame('approved', $fee->status);
        $this->assertTrue($fee->isFullyPaid());
    }
}


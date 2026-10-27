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

    public function test_admit_card_exports_filter_class_and_individual_and_download_pdf(): void
    {
        $env = $this->setUpEnvironment();
        $base = ['exam_id' => $env['exam']->id, 'school_id' => $env['school']->id,
            'status' => 'registered', 'approval_status' => 'approved'];
        $alice = McqRegistration::create($base + ['student_id' => $env['student1']->id, 'hall_ticket_no' => '101']);
        $bob = McqRegistration::create($base + ['student_id' => $env['student2']->id, 'hall_ticket_no' => '102']);
        $otherClass = SchoolClass::create(['tenant_id' => $env['school']->id, 'name' => 'Class 9', 'is_active' => true]);
        $env['student2']->update(['school_class_id' => $otherClass->id]);
        $url = "/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/hall-tickets/print-all";
        $this->actingAs($env['sahodayaAdmin'])->get($url.'?class=Class%2010')
            ->assertOk()->assertViewHas('registrations', fn ($rows) => $rows->modelKeys() === [$alice->id]);
        $this->get($url.'?registration_id='.$bob->id)
            ->assertOk()->assertViewHas('registrations', fn ($rows) => $rows->modelKeys() === [$bob->id]);
        $this->get($url.'?class=Unknown')->assertNotFound();

        config(['services.pdf_converter.url' => 'http://pdf-renderer.test/convert']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('%PDF-test', 200, ['Content-Type' => 'application/pdf'])]);
        $this->get($url.'?download=1&registration_id='.$alice->id)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request['html'], 'Alice Candidate')
            && ! str_contains($request['html'], 'Bob Candidate') && ! str_contains($request['html'], 'Print All'));
        $alice->update(['status' => 'cancelled']);
        $this->get($url.'?registration_id='.$alice->id)->assertNotFound();
        $bob->update(['hall_ticket_no' => null]);
        $this->get($url.'?registration_id='.$bob->id)->assertNotFound();
    }

    public function test_centre_code_auto_assignment_lists_schools_and_preserves_existing_codes(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $env = $this->setUpEnvironment();
        $other = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'AAA New School', 'parent_id' => $env['sahodaya']->id]);
        $env['exam']->update(['settings_json' => ['school_centre_codes' => [$env['school']->id => 'STSE-010']]]);
        $service = app(\App\Services\Mcq\McqSchoolCentreCodeService::class);
        $input = ['prefix' => 'STSE-', 'start' => 10, 'digits' => 3, 'scope' => 'all', 'replace' => false];
        $plan = collect($service->plan($env['exam']->fresh(), $input))->keyBy('id');
        $this->assertSame('STSE-010', $plan[$env['school']->id]['proposed']);
        $this->assertSame('STSE-011', $plan[$other->id]['proposed']);
        $this->assertArrayNotHasKey($other->id, $env['exam']->fresh()->settings_json['school_centre_codes']);
        $url = "/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/centre-codes";
        $this->actingAs($env['sahodayaAdmin'])->get($url.'?'.http_build_query($input + ['preview' => 1]))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Sahodaya/Mcq/CentreCodes', false)->has('assignmentPreview'));
        $this->actingAs($env['sahodayaAdmin'])->post($url.'/assign', $input)->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame('STSE-011', $env['exam']->fresh()->settings_json['school_centre_codes'][$other->id]);
        $service->assign($env['exam']->fresh(), $input);
        $this->assertSame('STSE-011', $env['exam']->fresh()->settings_json['school_centre_codes'][$other->id]);
        $service->assign($env['exam']->fresh(), array_merge($input, ['start' => 100, 'replace' => true]));
        $this->assertSame('STSE-100', $env['exam']->fresh()->settings_json['school_centre_codes'][$other->id]);
    }

    public function test_admin_assigns_unique_school_centre_codes_per_exam(): void
    {
        $env = $this->setUpEnvironment();
        $url = "/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/school-centre-code";
        $this->actingAs($env['sahodayaAdmin'])->post($url, ['school_id' => $env['school']->id, 'code' => 'STSE-001'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('STSE-001', \App\Support\Mcq\McqHallTicketDesign::centreCodeForSchool($env['exam']->fresh(), $env['school']));
        $otherSchool = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Second', 'parent_id' => $env['sahodaya']->id]);
        $this->actingAs($env['sahodayaAdmin'])->post($url, ['school_id' => $otherSchool->id, 'code' => 'stse-001'])
            ->assertSessionHasErrors('code');
        $this->assertNull(\App\Support\Mcq\McqHallTicketDesign::centreCodeForSchool($env['exam']->fresh(), $otherSchool));
        $this->actingAs($env['sahodayaAdmin'])->post($url, ['school_id' => $env['school']->id, 'code' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull(\App\Support\Mcq\McqHallTicketDesign::centreCodeForSchool($env['exam']->fresh(), $env['school']));
    }

    public function test_frontend_resets_exam_registration_numbers_without_approving_pending_payments(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $env = $this->setUpEnvironment();
        $registration = McqRegistration::create([
            'exam_id' => $env['exam']->id, 'school_id' => $env['school']->id,
            'student_id' => $env['student1']->id, 'status' => 'registered',
            'approval_status' => 'pending_payment', 'hall_ticket_no' => '999',
        ]);
        $class = $env['student1']->schoolClass->name;
        $url = "/sahodaya-admin/{$env['sahodaya']->id}/mcq-exams/{$env['exam']->id}/registration-numbers/reset";
        $this->actingAs($env['sahodayaAdmin'])->post($url, ['ranges' => [['class' => $class, 'start' => 12000]]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('12000', $registration->fresh()->hall_ticket_no);
        $this->assertSame('pending_payment', $registration->fresh()->approval_status);
        $this->assertSame(12000, $env['exam']->fresh()->settings_json['hall_ticket_class_starts'][$class]);

        $this->actingAs($env['sahodayaAdmin'])->post($url, ['ranges' => [['class' => $class, 'start' => 0]]])
            ->assertSessionHasErrors('ranges.0.start');
        $this->assertSame('12000', $registration->fresh()->hall_ticket_no);
        $this->actingAs($env['sahodayaAdmin'])->post($url, ['ranges' => [['class' => 'wrong class', 'start' => 10000]]])
            ->assertSessionHasErrors('ranges');
        $this->assertSame('12000', $registration->fresh()->hall_ticket_no);
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


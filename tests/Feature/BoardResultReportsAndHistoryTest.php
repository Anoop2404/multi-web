<?php

namespace Tests\Feature;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use App\Models\User;
use App\Services\BoardResults\BoardResultStudentHistoryService;
use App\Services\BoardResults\FullA1AchieversReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoardResultReportsAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_a1_achievers_report_service_returns_subject_marks()
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Sahodaya',
            'type' => 'sahodaya',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
        ]);

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => '2025-26',
            'examination_type' => 'AISSE',
            'status' => BoardResult::STATUS_SUBMITTED,
        ]);

        $topper = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_FULL_A1,
            'name' => 'John Doe',
            'roll_no' => '10001',
            'admission_no' => 'ADM101',
            'gender' => 'male',
        ]);

        TopperSubjectMark::create([
            'topper_id' => $topper->id,
            'tenant_id' => $school->id,
            'subject_label' => 'English Communicative',
            'marks' => 95,
        ]);

        TopperSubjectMark::create([
            'topper_id' => $topper->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Mathematics Standard',
            'marks' => 98,
        ]);

        $service = new FullA1AchieversReportService();
        $list = $service->list($sahodaya->id, '2025-26');

        $this->assertCount(1, $list);
        $this->assertEquals('John Doe', $list[0]['student_name']);
        $this->assertCount(2, $list[0]['subject_marks']);
        $this->assertEquals(95, $list[0]['subject_marks'][0]['marks']);
        $this->assertEquals('A1', $list[0]['subject_marks'][0]['grade']);
    }

    public function test_student_history_service_aggregates_historical_performance()
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Sahodaya',
            'type' => 'sahodaya',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
        ]);

        // Class 10 Result
        $br10 = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => '2023-24',
            'examination_type' => 'AISSE',
            'status' => BoardResult::STATUS_APPROVED,
        ]);

        $t10 = Topper::create([
            'board_result_id' => $br10->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_FULL_A1,
            'name' => 'Jane Smith',
            'roll_no' => '20002',
            'admission_no' => 'ADM202',
            'gender' => 'female',
        ]);

        TopperSubjectMark::create([
            'topper_id' => $t10->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Science',
            'marks' => 96,
        ]);

        // Class 12 Result
        $br12 = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => '2025-26',
            'examination_type' => 'AISSCE',
            'status' => BoardResult::STATUS_APPROVED,
        ]);

        $t12 = Topper::create([
            'board_result_id' => $br12->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_OVERALL,
            'name' => 'Jane Smith',
            'roll_no' => '20002',
            'admission_no' => 'ADM202',
            'gender' => 'female',
            'stream' => 'Science',
            'percentage' => 97.4,
            'rank' => 1,
        ]);

        TopperSubjectMark::create([
            'topper_id' => $t12->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Physics',
            'marks' => 98,
        ]);

        $service = new BoardResultStudentHistoryService();
        $result = $service->search('20002', sahodayaId: $sahodaya->id);

        $this->assertCount(1, $result['matches']);
        $match = $result['matches'][0];
        $this->assertEquals('Jane Smith', $match['student_name']);
        $this->assertCount(2, $match['history']);

        // Check history timeline order (latest first)
        $this->assertEquals('2025-26', $match['history'][0]['academic_year']);
        $this->assertEquals(12, $match['history'][0]['class']);
        $this->assertEquals(97.4, $match['history'][0]['percentage']);

        $this->assertEquals('2023-24', $match['history'][1]['academic_year']);
        $this->assertEquals(10, $match['history'][1]['class']);
    }

    public function test_sahodaya_and_school_student_history_api_endpoints()
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Sahodaya',
            'type' => 'sahodaya',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
        ]);

        \Spatie\Permission\Models\Role::findOrCreate('sahodaya_admin');
        \Spatie\Permission\Models\Role::findOrCreate('school_admin');

        $sahodayaAdmin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sahodaya Admin User',
            'email' => 'sahodaya_admin@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $schoolAdmin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'School Admin User',
            'email' => 'school_admin@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $schoolAdmin->assignRole('school_admin');

        $br = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => '2025-26',
            'examination_type' => 'AISSE',
            'status' => BoardResult::STATUS_APPROVED,
        ]);

        $topper = Topper::create([
            'board_result_id' => $br->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_FULL_A1,
            'name' => 'Alice Walker',
            'roll_no' => '30003',
            'admission_no' => 'ADM303',
            'gender' => 'female',
        ]);

        // Test Sahodaya endpoint authenticated
        $res1 = $this->actingAs($sahodayaAdmin)->getJson("/sahodaya-admin/{$sahodaya->id}/board-results/student-history?query=Alice");
        $res1->assertOk();
        $res1->assertJsonPath('matches.0.student_name', 'Alice Walker');

        // Test School endpoint authenticated
        $res2 = $this->actingAs($schoolAdmin)->getJson("/school-admin/{$school->id}/board-results/student-history?query=30003");
        $res2->assertOk();
        $res2->assertJsonPath('matches.0.roll_no', '30003');
    }

    public function test_full_a1_achiever_can_be_saved_when_student_already_exists_in_overall_toppers()
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Sahodaya',
            'type' => 'sahodaya',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test School',
            'type' => 'school',
            'parent_id' => $sahodaya->id,
        ]);

        \Spatie\Permission\Models\Role::findOrCreate('school_admin');

        $schoolAdmin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'School Admin User',
            'email' => 'school_admin_test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $schoolAdmin->assignRole('school_admin');

        $br = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => '2025-26',
            'examination_type' => 'AISSE',
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        // Student registered as OVERALL Topper first
        Topper::create([
            'board_result_id' => $br->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_OVERALL,
            'name' => 'Ronith Joy K',
            'roll_no' => '24162991',
            'admission_no' => 'ADM241',
            'gender' => 'male',
            'rank' => 1,
            'percentage' => 98.2,
        ]);

        // Now save the same student in Full A1 Achievers batch
        $response = $this->actingAs($schoolAdmin)
            ->post("/school-admin/{$school->id}/board-results/{$br->id}/full-a1-achievers/batch", [
                'rows' => [
                    [
                        'name' => 'Ronith Joy K',
                        'gender' => 'male',
                        'roll_no' => '24162991',
                        'subject_marks' => [
                            ['subject' => 'English', 'marks' => 95],
                            ['subject' => 'Mathematics', 'marks' => 99],
                        ],
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify Full A1 topper record was created alongside overall topper record
        $this->assertDatabaseHas('toppers', [
            'board_result_id' => $br->id,
            'roll_no' => '24162991',
            'entry_type' => Topper::ENTRY_FULL_A1,
        ]);
    }
}

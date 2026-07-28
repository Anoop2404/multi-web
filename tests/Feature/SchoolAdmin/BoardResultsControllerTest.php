<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoardResultsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createSchoolContext(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'board-results.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'TS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $year = AcademicYearRecord::create([
            'label' => '2026-27',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'year', 'admin');
    }

    public function test_subject_wise_batch_updates_existing_topper_name_and_marks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSCE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $existing = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'name' => 'Original Student',
            'roll_no' => '1001',
            'gender' => 'male',
            'percentage' => 91,
            'total_marks' => 100,
            'marks_obtained' => 91,
            'rank' => 1,
        ]);

        TopperSubjectMark::create([
            'topper_id' => $existing->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Physics',
            'marks' => 90,
        ]);

        $response = $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult->id}/subject-toppers/batch", [
            'subject' => 'Physics',
            'rows' => [
                [
                    'name' => 'Updated Student',
                    'gender' => 'female',
                    'roll_no' => '1001',
                    'marks' => 98,
                ],
                [
                    'name' => 'New Subject Leader',
                    'gender' => 'female',
                    'roll_no' => '1002',
                    'marks' => 96,
                ],
            ],
        ]);

        $response->assertRedirect();

        $existing->refresh();
        $this->assertSame('Updated Student', $existing->name);
        $this->assertSame('female', $existing->gender);
        $this->assertSame('1001', $existing->roll_no);
        $this->assertSame(98.0, (float) $existing->subject_marks['Physics']);

        $created = Topper::query()
            ->where('board_result_id', $boardResult->id)
            ->where('roll_no', '1002')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame('New Subject Leader', $created->name);
        $this->assertSame(96.0, (float) $created->percentage);
        $this->assertSame(100, (int) $created->total_marks);
        $this->assertSame(96.0, (float) $created->subject_marks['Physics']);
    }

    public function test_class_xii_overall_topper_rejects_invalid_stream_key(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSCE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult->id}/toppers/single", [
            'name' => 'Invalid Stream Student',
            'gender' => 'male',
            'roll_no' => '2001',
            'percentage' => 95,
            'marks_obtained' => 475,
            'total_marks' => 500,
            'stream_key' => 'not-a-real-stream',
            'subject_marks' => [],
        ]);

        $response->assertSessionHasErrors('stream_key');
        $this->assertDatabaseCount('toppers', 0);
    }
}

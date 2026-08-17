<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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

    /** @return array<int, array<string, mixed>> */
    private function topperRows(string $prefix, string $streamKey, int $count, int $maxMarks, int $startRoll = 1001): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'name' => "{$prefix} Student ".($i + 1),
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'roll_no' => (string) ($startRoll + $i),
                'marks_obtained' => $maxMarks - $i,
                'stream_key' => $streamKey,
            ];
        }

        return $rows;
    }

    public function test_board_entry_window_year_is_listed_without_an_academic_year_master_record(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'admin' => $admin] = $this->createSchoolContext();

        SahodayaRegistrationWindow::create([
            'sahodaya_id' => $sahodaya->id,
            'academic_year' => '2025-26',
            'board_entry_starts_at' => now()->subDay()->toDateString(),
            'board_entry_ends_at' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/board-results?class=12");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('School/BoardResults/Workspace', false)
            ->has('academicYearOptions', 2)
            ->where('academicYearOptions.1.label', '2025-26')
            ->where('academicYearOptions.1.entry_status', 'open')
            ->where('academicYearOptions.1.entry_configured', true));
    }

    public function test_open_board_entry_dates_allow_entry_for_a_closed_academic_year(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        $year->update(['status' => 'closed']);
        SahodayaRegistrationWindow::create([
            'sahodaya_id' => $sahodaya->id,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'board_entry_starts_at' => now()->subDay()->toDateString(),
            'board_entry_ends_at' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/board-results", [
                'class' => 12,
                'academic_year' => $year->label,
                'total_appeared' => 10,
                'pass_count' => 10,
            ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('board_results', [
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => $year->label,
        ]);
    }

    public function test_expired_board_entry_dates_block_entry_even_for_an_active_academic_year(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        SahodayaRegistrationWindow::create([
            'sahodaya_id' => $sahodaya->id,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'board_entry_starts_at' => now()->subDays(10)->toDateString(),
            'board_entry_ends_at' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($admin)
            ->post("/school-admin/{$school->id}/board-results", [
                'class' => 12,
                'academic_year' => $year->label,
                'total_appeared' => 10,
                'pass_count' => 10,
            ]);

        $response->assertSessionHasErrors('academic_year');
        $this->assertDatabaseMissing('board_results', [
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => $year->label,
        ]);
    }

    public function test_submitted_result_remains_editable_until_board_entry_window_ends(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        SahodayaRegistrationWindow::create([
            'sahodaya_id' => $sahodaya->id,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'board_entry_starts_at' => now()->subWeek()->toDateString(),
            'board_entry_ends_at' => now()->addWeek()->toDateString(),
        ]);

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSE,
            'status' => BoardResult::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(3),
        ]);

        $this->assertTrue($boardResult->isEditable());
        $this->assertNull($boardResult->editLockReason());

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/board-results/{$boardResult->id}/toppers")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEdit', true)
                ->where('editLockReason', null));
    }

    public function test_overall_topper_percentage_is_always_derived_from_marks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['school' => $school, 'year' => $year] = $this->createSchoolContext();

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $topper = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_OVERALL,
            'name' => 'Percentage Check',
            'percentage' => 96.8,
            'marks_obtained' => 402,
            'total_marks' => 500,
            'rank' => 1,
        ]);

        $this->assertSame(80.4, (float) $topper->fresh()->percentage);

        $topper->update(['marks_obtained' => 484]);
        $this->assertSame(96.8, (float) $topper->fresh()->percentage);
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
            'entry_type' => Topper::ENTRY_SUBJECT,
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
        $this->assertSame(Topper::ENTRY_SUBJECT, $created->entry_type);
        $this->assertNull($created->percentage);
        $this->assertNull($created->total_marks);
        $this->assertNull($created->stream);
        $this->assertSame(96.0, (float) $created->subject_marks['Physics']);

        $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult->id}/subject-toppers/batch", [
            'subject' => 'Physics',
            'rows' => [[
                'topper_id' => $created->id,
                'original_subject' => 'Physics',
                'name' => 'Revised Subject Leader',
                'gender' => 'female',
                'roll_no' => '1002',
                'marks' => 99,
            ]],
        ])->assertRedirect();

        $created->refresh();
        $this->assertSame('Revised Subject Leader', $created->name);
        $this->assertSame(99.0, (float) $created->subject_marks['Physics']);
        // Omitting 1001 from the Physics batch removed 1001 from Physics, leaving 1 topper row
        $this->assertSame(1, Topper::where('board_result_id', $boardResult->id)->count());

        $this->actingAs($admin)->put(
            "/school-admin/{$school->id}/board-results/{$boardResult->id}/toppers/{$created->id}",
            ['subject_marks' => []],
        )->assertRedirect();

        $this->assertDatabaseMissing('toppers', ['id' => $created->id]);
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

    public function test_bulk_toppers_support_ten_rows_for_class_x_and_all_class_xii_streams(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        $sahodayaAdmin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $streams = [
            'science' => 'Science',
            'commerce' => 'Commerce',
            'humanities' => 'Humanities',
        ];

        foreach ($streams as $key => $label) {
            \App\Models\ExamStream::create([
                'sahodaya_id' => $sahodaya->id,
                'code' => $key,
                'label' => $label,
                'examination_type' => BoardResult::EXAM_AISSCE,
                'sort_order' => match ($key) {
                    'science' => 1,
                    'commerce' => 2,
                    default => 3,
                },
                'is_active' => true,
                'default_subjects' => ['English core'],
            ]);

            \App\Models\BoardResultMarksConfig::create([
                'sahodaya_id' => $sahodaya->id,
                'class' => 12,
                'stream_id' => \App\Models\ExamStream::findByCode($key, $sahodaya->id)->id,
                'total_marks' => 500,
            ]);
        }

        $boardResult10 = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $boardResult12 = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSCE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $classXRows = [];
        for ($i = 1; $i <= 10; $i++) {
            $classXRows[] = [
                'name' => "Class X Student {$i}",
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'roll_no' => (string) (2000 + $i),
                'marks_obtained' => 495 - $i,
            ];
        }

        $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult10->id}/toppers/batch", [
            'toppers' => $classXRows,
        ])->assertRedirect();

        $classXiiRows = array_merge(
            $this->topperRows('Science', 'science', 10, 500, 3001),
            $this->topperRows('Commerce', 'commerce', 10, 480, 4001),
            $this->topperRows('Humanities', 'humanities', 10, 470, 5001),
        );

        $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult12->id}/toppers/batch", [
            'toppers' => $classXiiRows,
        ])->assertRedirect();

        $subjectRows = [];
        for ($i = 1; $i <= 10; $i++) {
            $subjectRows[] = [
                'name' => "Physics Student {$i}",
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'roll_no' => (string) (6000 + $i),
                'marks' => 100 - $i,
            ];
        }

        $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult12->id}/subject-toppers/batch", [
            'subject' => 'Physics',
            'rows' => $subjectRows,
        ])->assertRedirect();

        $this->assertSame(40, Topper::where('board_result_id', $boardResult12->id)->count());
        $this->assertSame(10, TopperSubjectMark::whereIn('topper_id', Topper::where('board_result_id', $boardResult12->id)->pluck('id'))->count());

        $this->assertSame(10, Topper::where('board_result_id', $boardResult10->id)->count());
        $this->assertSame(40, Topper::where('board_result_id', $boardResult12->id)->count());
        $this->assertSame(10, Topper::where('board_result_id', $boardResult12->id)
            ->where('entry_type', Topper::ENTRY_OVERALL)
            ->where('stream', 'Science')
            ->count());
        $this->assertSame(10, Topper::where('board_result_id', $boardResult12->id)
            ->where('entry_type', Topper::ENTRY_SUBJECT)
            ->count());
        $this->assertSame(10, Topper::where('board_result_id', $boardResult12->id)->where('stream', 'Commerce')->count());
        $this->assertSame(10, Topper::where('board_result_id', $boardResult12->id)->where('stream', 'Humanities')->count());

        $boardResult12->update(['status' => BoardResult::STATUS_SUBMITTED]);

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/board-results/{$boardResult10->id}/toppers")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/BoardResults/Workspace', false)
                ->where('topperCount', 10)
                ->where('isClass12', false));

        $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/board-results/{$boardResult12->id}/toppers")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/BoardResults/Workspace', false)
                ->where('topperCount', 30)
                ->where('isClass12', true));

        $this->actingAs($sahodayaAdmin)
            ->get("/sahodaya-admin/{$sahodaya->id}/board-results/toppers/overall?class=12&academic_year={$year->label}&stream=science")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/BoardResults/TopperResultsOverall', false)
                ->has('rows', 10)
                ->where('rows.0.student_name', 'Science Student 1'));

        $this->actingAs($sahodayaAdmin)
            ->get("/sahodaya-admin/{$sahodaya->id}/board-results/toppers/subject-wise?academic_year={$year->label}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/BoardResults/TopperResultsSubjectWise', false)
                ->has('subjectLeaders', 10)
                ->where('subjectLeaders.0.subject', 'Physics'));

        $boardResult12->update(['status' => BoardResult::STATUS_APPROVED]);

        $this->actingAs($sahodayaAdmin)
            ->get("/sahodaya-admin/{$sahodaya->id}/board-results/reports/subject-merit?academic_year={$year->label}&class=12")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/BoardResults/SubjectMeritRegister', false)
                ->where('rows', fn ($rows) => count($rows) === 10)
                ->where('rows.0.subject', 'Physics')
                ->where('rows.0.class', 12));

        $this->actingAs($sahodayaAdmin)
            ->get("/sahodaya-admin/{$sahodaya->id}/board-results/verification?status=all")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/BoardResults/Verification', false)
                ->where('results.total', 2)
                ->where('schoolNames.'.$school->id, 'Test School'));
    }

    public function test_subject_wise_batch_matches_existing_topper_by_roll_no_even_when_admission_no_is_passed_or_unmatched(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $existing = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'RAFA FATHIMA V C',
            'roll_no' => '24640189',
            'admission_no' => null,
            'gender' => 'female',
            'rank' => 1,
        ]);

        TopperSubjectMark::create([
            'topper_id' => $existing->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Mathematics',
            'marks' => 95,
        ]);

        $response = $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult->id}/subject-toppers/batch", [
            'subject' => 'Science',
            'rows' => [
                [
                    'name' => 'RAFA FATHIMA V C',
                    'gender' => 'female',
                    'roll_no' => '24640189',
                    'admission_no' => 'ADM-999',
                    'marks' => 98,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Topper::where('board_result_id', $boardResult->id)->subjectEntries()->count());

        $existing->refresh();
        $this->assertSame('24640189', $existing->roll_no);
        $this->assertSame('ADM-999', $existing->admission_no);
        $this->assertSame(95.0, (float) $existing->subject_marks['Mathematics']);
        $this->assertSame(98.0, (float) $existing->subject_marks['Science']);
    }

    public function test_subject_wise_batch_returns_validation_error_when_roll_no_conflicts_with_another_student(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['school' => $school, 'year' => $year, 'admin' => $admin] = $this->createSchoolContext();

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Student One',
            'roll_no' => '24640189',
            'gender' => 'female',
            'rank' => 1,
        ]);

        Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Student Two',
            'roll_no' => '24640190',
            'gender' => 'male',
            'rank' => 2,
        ]);

        $response = $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult->id}/subject-toppers/batch", [
            'subject' => 'Science',
            'rows' => [
                [
                    'topper_id' => Topper::where('roll_no', '24640190')->first()->id,
                    'name' => 'Student Two',
                    'gender' => 'male',
                    'roll_no' => '24640189',
                    'marks' => 90,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['rows.0.roll_no']);
    }

    public function test_subject_wise_batch_prevents_duplicate_roll_no_when_updating_student_previously_without_roll_no(): void
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

        Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Existing Student With Roll',
            'roll_no' => '24641621',
            'gender' => 'female',
            'rank' => 1,
        ]);

        $studentWithoutRoll = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'ARATHY M V',
            'roll_no' => null,
            'gender' => 'female',
            'rank' => 2,
        ]);

        $response = $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult->id}/subject-toppers/batch", [
            'subject' => 'Malayalam',
            'rows' => [
                [
                    'topper_id' => $studentWithoutRoll->id,
                    'name' => 'ARATHY M V',
                    'gender' => 'female',
                    'roll_no' => '24641621',
                    'marks' => 98,
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_subject_wise_batch_removes_omitted_students_for_subject(): void
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

        $studentA = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Student A',
            'roll_no' => '1001',
            'gender' => 'female',
            'rank' => 1,
        ]);
        TopperSubjectMark::create(['topper_id' => $studentA->id, 'tenant_id' => $school->id, 'subject_label' => 'Malayalam', 'marks' => 95]);

        $studentB = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Student B',
            'roll_no' => '1002',
            'gender' => 'male',
            'rank' => 2,
        ]);
        TopperSubjectMark::create(['topper_id' => $studentB->id, 'tenant_id' => $school->id, 'subject_label' => 'Malayalam', 'marks' => 90]);

        $response = $this->actingAs($admin)->post("/school-admin/{$school->id}/board-results/{$boardResult->id}/subject-toppers/batch", [
            'subject' => 'Malayalam',
            'rows' => [
                [
                    'topper_id' => $studentA->id,
                    'name' => 'Student A',
                    'gender' => 'female',
                    'roll_no' => '1001',
                    'marks' => 95,
                ],
            ],
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('toppers', ['id' => $studentA->id]);
        $this->assertDatabaseMissing('toppers', ['id' => $studentB->id]);
    }

    public function test_subject_topper_individual_removal_succeeds_without_percentage_field(): void
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

        $student = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_SUBJECT,
            'name' => 'Multi Subject Student',
            'roll_no' => '1003',
            'gender' => 'female',
            'percentage' => null,
            'rank' => 1,
        ]);
        TopperSubjectMark::create(['topper_id' => $student->id, 'tenant_id' => $school->id, 'subject_label' => 'Physics', 'marks' => 98]);
        TopperSubjectMark::create(['topper_id' => $student->id, 'tenant_id' => $school->id, 'subject_label' => 'Malayalam', 'marks' => 92]);

        $response = $this->actingAs($admin)->put("/school-admin/{$school->id}/board-results/{$boardResult->id}/toppers/{$student->id}", [
            'id' => $student->id,
            'name' => 'Multi Subject Student',
            'gender' => 'female',
            'roll_no' => '1003',
            'percentage' => null,
            'subject_marks' => ['Physics' => 98],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('toppers', ['id' => $student->id]);
        $this->assertDatabaseMissing('topper_subject_marks', ['topper_id' => $student->id, 'subject_label' => 'Malayalam']);
        $this->assertDatabaseHas('topper_subject_marks', ['topper_id' => $student->id, 'subject_label' => 'Physics']);
    }
}

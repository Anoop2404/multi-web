<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Mcq\McqSchoolLevelAwardService;
use App\Support\Mcq\McqSchoolLevelAwardSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class McqSchoolLevelAwardServiceTest extends TestCase
{
    use RefreshDatabase;

    private McqSchoolLevelAwardService $service;

    private string $sahodayaId;

    private string $schoolId;

    private McqExam $exam;

    private SchoolClass $lkg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(McqSchoolLevelAwardService::class);
        $this->sahodayaId = (string) Str::uuid();
        $this->schoolId = (string) Str::uuid();

        Tenant::create([
            'id' => $this->sahodayaId,
            'type' => 'sahodaya',
            'name' => 'MCS',
            'is_active' => true,
        ]);
        Tenant::create([
            'id' => $this->schoolId,
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $this->sahodayaId,
            'is_active' => true,
        ]);

        $this->exam = McqExam::create([
            'tenant_id' => $this->sahodayaId,
            'title' => 'STSE 2026',
            'exam_type' => 'assessment',
            'status' => 'ongoing',
            'delivery_mode' => 'offline',
            'total_questions' => 25,
            'settings_json' => [
                'product_mode' => 'talent_search',
                'school_level_awards' => [
                    'enabled' => true,
                    'preset' => McqSchoolLevelAwardSettings::PRESET_STSE_KG_SETTING_1,
                    'kg' => McqSchoolLevelAwardSettings::presetKg(McqSchoolLevelAwardSettings::PRESET_STSE_KG_SETTING_1),
                    'submissions' => [],
                ],
            ],
        ]);

        $this->lkg = SchoolClass::create([
            'tenant_id' => $this->schoolId,
            'name' => 'LKG',
            'is_active' => true,
        ]);
    }

    public function test_quota_is_exact_fifteen_percent_rounded(): void
    {
        $this->assertSame(3, $this->service->quota(20, 15));
        $this->assertSame(15, $this->service->quota(100, 15));
        $this->assertSame(2, $this->service->quota(10, 15));
        $this->assertSame(0, $this->service->quota(2, 15));
    }

    public function test_promote_succeeds_with_unique_top_three_and_exact_quota(): void
    {
        // 20 present → quota 3. Distinct scores for ranks 1–3.
        $scores = range(20, 1); // 20 down to 1
        foreach ($scores as $i => $score) {
            $this->seedPresentMarkedStudent('Child '.$i, (float) $score);
        }

        $awarded = $this->service->promote($this->exam, $this->schoolId, 'LKG', 1);

        $this->assertCount(3, $awarded);
        $this->assertTrue(McqSchoolLevelAwardSettings::isClassSubmitted($this->exam->fresh(), $this->schoolId, 'LKG'));
    }

    public function test_promote_blocks_when_rank_one_is_tied(): void
    {
        foreach ([10, 10, 8, 7, 6, 5, 4, 3, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1] as $i => $score) {
            $this->seedPresentMarkedStudent('Tie '.$i, (float) $score);
        }

        try {
            $this->service->promote($this->exam, $this->schoolId, 'LKG', 1);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                McqSchoolLevelAwardSettings::TIE_ERROR,
                $e->errors()['promote'][0] ?? null,
            );
        }
    }

    public function test_promote_blocks_when_cutoff_border_has_ties(): void
    {
        // 20 students, quota 3. Scores: 10, 9, 8, 8, … → including both 8s would make 4.
        $scores = [10, 9, 8, 8, 7, 6, 5, 4, 3, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1];
        foreach ($scores as $i => $score) {
            $this->seedPresentMarkedStudent('Border '.$i, (float) $score);
        }

        try {
            $this->service->promote($this->exam, $this->schoolId, 'LKG', 1);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                McqSchoolLevelAwardSettings::TIE_ERROR,
                $e->errors()['promote'][0] ?? null,
            );
        }
    }

    public function test_ties_below_rank_three_are_allowed_when_cutoff_is_clear(): void
    {
        // quota 3; ranks 1–3 unique; ranks 4+ tied at score 5 — OK.
        $scores = [12, 11, 10, 5, 5, 5, 4, 3, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1];
        foreach ($scores as $i => $score) {
            $this->seedPresentMarkedStudent('OkTie '.$i, (float) $score);
        }

        $awarded = $this->service->promote($this->exam, $this->schoolId, 'LKG', 1);
        $this->assertCount(3, $awarded);
    }

    public function test_marks_locked_after_submit(): void
    {
        foreach ([10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1] as $i => $score) {
            $this->seedPresentMarkedStudent('Lock '.$i, (float) $score);
        }

        $this->service->promote($this->exam, $this->schoolId, 'LKG', 1);
        $exam = $this->exam->fresh();

        $this->assertFalse($this->service->schoolCanEnterMarks($exam, $this->schoolId, 'LKG'));

        $reg = McqRegistration::where('exam_id', $exam->id)->where('school_id', $this->schoolId)->first();
        $this->expectException(ValidationException::class);
        $this->service->assertMarkEditable($exam, $reg->load('student.schoolClass'));
    }

    public function test_unlock_allows_re_edit(): void
    {
        foreach ([10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1] as $i => $score) {
            $this->seedPresentMarkedStudent('Unlock '.$i, (float) $score);
        }

        $this->service->promote($this->exam, $this->schoolId, 'LKG', 1);
        $this->service->unlock($this->exam->fresh(), $this->schoolId, 'LKG', 1);

        $this->assertTrue($this->service->schoolCanEnterMarks($this->exam->fresh(), $this->schoolId, 'LKG'));
    }

    private function seedPresentMarkedStudent(string $name, float $score): void
    {
        $student = Student::create([
            'tenant_id' => $this->schoolId,
            'school_class_id' => $this->lkg->id,
            'name' => $name,
            'status' => 'active',
        ]);

        $registration = McqRegistration::create([
            'exam_id' => $this->exam->id,
            'student_id' => $student->id,
            'school_id' => $this->schoolId,
            'status' => 'submitted',
            'attendance_status' => 'present',
            'approval_status' => 'approved',
            'hall_ticket_no' => (string) random_int(10000, 99999),
        ]);

        McqMark::create([
            'registration_id' => $registration->id,
            'correct_count' => (int) $score,
            'wrong_count' => 0,
            'unanswered_count' => 0,
            'score' => $score,
            'percentage' => ($score / 25) * 100,
            'grade' => 'A',
        ]);
    }
}

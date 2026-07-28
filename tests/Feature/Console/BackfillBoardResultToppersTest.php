<?php

namespace Tests\Feature\Console;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultMarksConfig;
use App\Models\ExamStream;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillBoardResultToppersTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_updates_legacy_class_xii_toppers(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'backfill.test',
            'is_active' => true,
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $science = ExamStream::create([
            'sahodaya_id' => $sahodaya->id,
            'code' => 'science',
            'label' => 'Science',
            'examination_type' => 'AISSCE',
            'sort_order' => 1,
            'is_active' => true,
            'default_subjects' => ['Physics', 'Chemistry'],
        ]);

        BoardResultMarksConfig::create([
            'sahodaya_id' => $sahodaya->id,
            'class' => 12,
            'stream_id' => $science->id,
            'total_marks' => 200,
        ]);

        $year = AcademicYearRecord::create([
            'label' => '2026-27',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
        ]);

        $boardResult = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 12,
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'examination_type' => BoardResult::EXAM_AISSCE,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        $overall = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'name' => 'Overall Legacy',
            'stream' => 'SCIENCE',
            'total_marks' => 500,
            'marks_obtained' => 180,
            'percentage' => 36,
            'rank' => 1,
        ]);

        $subjectWise = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'name' => 'Subject Legacy',
            'total_marks' => 500,
            'marks_obtained' => 98,
            'percentage' => 19.6,
            'rank' => 2,
        ]);

        TopperSubjectMark::create([
            'topper_id' => $subjectWise->id,
            'tenant_id' => $school->id,
            'subject_label' => 'Physics',
            'marks' => 98,
        ]);

        $unrelatedClass10 = Topper::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $school->id,
            'name' => 'Class Ten',
            'total_marks' => 500,
            'marks_obtained' => 470,
            'percentage' => 94,
            'rank' => 3,
        ]);

        $this->artisan('board-results:backfill-legacy-toppers', [
            '--tenant' => $sahodaya->id,
        ])->assertExitCode(0);

        $overall->refresh();
        $this->assertSame($science->id, $overall->stream_id);
        $this->assertSame('Science', $overall->stream);
        $this->assertSame(200, (int) $overall->total_marks);
        $this->assertSame(90.0, (float) $overall->percentage);

        $subjectWise->refresh();
        $this->assertSame(100, (int) $subjectWise->total_marks);
        $this->assertSame(98.0, (float) $subjectWise->percentage);

        $unrelatedClass10->refresh();
        $this->assertSame(500, (int) $unrelatedClass10->total_marks);
        $this->assertSame(94.0, (float) $unrelatedClass10->percentage);
    }
}

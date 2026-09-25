<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventReportAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Student-wise ("school-wise student") report: standby entrants are left out, and the PDF can
 * be printed without the Rank / Mark / Grade columns (?hide_marks=1).
 */
class FestStudentWiseNoMarksAndStandbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_standby_entrants_are_left_out_of_the_student_rows(): void
    {
        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Standby Sahodaya', 'domain' => 'standby-sw.test', 'is_active' => true]);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Standby School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '8']);
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Standby Fest', 'event_type' => 'kalolsavam', 'status' => 'published']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo', 'participant_type' => 'individual', 'is_enabled' => true]);

        foreach (['Main Student' => 'performer', 'Reserve Student' => 'standby'] as $name => $role) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => $name, 'status' => 'active']);
            $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => $role, 'chest_no' => 1]);
        }

        $names = collect((new FestEventReportAnalyticsService($event))->studentWiseBrowserRows())->pluck('name')->all();

        $this->assertSame(['Main Student'], $names);
    }

    public function test_marks_columns_can_be_left_off_the_student_wise_pdf(): void
    {
        $student = [
            'student_id' => 1, 'school_id' => 's1', 'school_name' => 'Alpha School', 'school_code' => 'AL', 'name' => 'Amy', 'reg_no' => 'R1',
            'item_count' => 1, 'items' => [[
                'item_title' => 'Solo', 'head_name' => null, 'category_label' => 'Cat 1', 'stage_type' => 'on_stage', 'participant_type' => 'individual',
                'status' => 'approved', 'chest_no' => 5, 'results_published' => true, 'grade' => 'A', 'position' => 1, 'score' => '90',
            ]],
        ];
        $render = fn (bool $showMarks) => view('fest.reports.student-wise', [
            'event' => (object) ['title' => 'E'], 'students' => [$student], 'showChestNo' => false, 'showMarks' => $showMarks,
            'bySchool' => true, 'orgName' => 'S', 'logoSrc' => null,
        ])->render();

        $with = $render(true);
        foreach (['Rank', 'Mark', 'Grade'] as $col) {
            $this->assertStringContainsString(">{$col}<", $with);
        }

        $without = $render(false);
        foreach (['Rank', 'Mark', 'Grade'] as $col) {
            $this->assertStringNotContainsString(">{$col}<", $without);
        }
        $this->assertStringNotContainsString('Result Pending', $without);
        $this->assertStringContainsString('Item Name', $without);
        $this->assertStringContainsString('Alpha School', $without);
    }
}

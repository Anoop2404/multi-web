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

    /**
     * The school-wise download (by_school=1): one section per school, its items underneath with
     * each item's participants -- standby entrants excluded, no photos.
     */
    public function test_school_wise_pdf_groups_schools_then_items_and_skips_standby(): void
    {
        $sahodaya = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'SW Sahodaya', 'domain' => 'sw-items.test', 'is_active' => true]);
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'SW Fest', 'event_type' => 'kalolsavam', 'status' => 'published']);
        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Alpha Item', 'participant_type' => 'individual', 'is_enabled' => true, 'gender' => 'female']);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Beta Group Item', 'participant_type' => 'group', 'is_enabled' => true]);

        $schools = [];
        foreach (['Zeta School', 'Alpha School'] as $name) {
            $schools[$name] = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => $name, 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
            $class = SchoolClass::create(['tenant_id' => $schools[$name]->id, 'name' => '6']);
            foreach ([[$itemA, "{$name} Ann", 'performer'], [$itemB, "{$name} Bea", 'performer'], [$itemB, "{$name} Reserve", 'standby']] as [$item, $student, $role]) {
                $st = Student::create(['tenant_id' => $schools[$name]->id, 'school_class_id' => $class->id, 'name' => $student, 'status' => 'active']);
                $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schools[$name]->id, 'status' => 'approved']);
                FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $st->id, 'participant_type' => 'student', 'participant_role' => $role, 'chest_no' => 1]);
            }
        }

        $service = new \App\Services\Events\FestReportService($event);
        $html = $service->export('student-wise-pdf', new \Illuminate\Http\Request(['by_school' => 1, 'inline' => 1]))->getContent();

        // Schools A-Z, each with its items (A-Z) and participants; the standby is nowhere.
        $this->assertMatchesRegularExpression('/ALPHA SCHOOL.*?Alpha Item.*?Alpha School Ann.*?Beta Group Item.*?Alpha School Bea.*?ZETA SCHOOL.*?Zeta School Ann.*?Zeta School Bea/s', $html);
        $this->assertStringNotContainsString('Reserve', $html);
        $this->assertStringContainsString('Girls', $html);
        $this->assertStringContainsString('Group', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('>Grade<', $html);

        // One school only, and without the marks columns.
        $one = $service->export('student-wise-pdf', new \Illuminate\Http\Request(['by_school' => 1, 'inline' => 1, 'school_id' => $schools['Zeta School']->id, 'hide_marks' => 1]))->getContent();
        $this->assertStringContainsString('ZETA SCHOOL', $one);
        $this->assertStringNotContainsString('ALPHA SCHOOL', $one);
        $this->assertStringNotContainsString('>Grade<', $one);
    }

    /**
     * With the Chromium header/footer the converter honours the view's own @page margin, so
     * it has to leave room for that header -- a 25px margin let the body start under it and
     * the repeating header was drawn over the first school heading.
     */
    public function test_chromium_header_views_reserve_top_margin_for_the_header(): void
    {
        foreach (['school-wise-items' => ['rows' => []], 'team-managers' => ['schools' => []], 'team-managers-registration-sheet' => ['schools' => []]] as $view => $data) {
            $base = ['event' => (object) ['title' => 'E'], 'orgName' => 'S', 'logoSrc' => null] + $data;

            $chrome = view("fest.reports.{$view}", $base + ['isDomPdf' => false, 'preview' => false])->render();
            $this->assertStringContainsString('margin: 32mm 10mm 14mm 10mm', $chrome, $view);

            $inPage = view("fest.reports.{$view}", $base + ['isDomPdf' => true, 'preview' => false])->render();
            $this->assertStringContainsString('margin: 25px 30px 25px 30px', $inPage, $view);
        }
    }
}

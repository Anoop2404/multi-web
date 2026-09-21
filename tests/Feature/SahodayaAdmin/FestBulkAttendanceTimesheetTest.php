<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestCompetitionArea;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Bulk Sheets picker's item_ids/phase_id/area_id filters (previously
 * FestMarkEntryController-only, see FestMarkEntrySheetBulkTest) now also apply to the
 * Attendance Sheet and Timesheet PDF exports (FestReportService::attendanceSheetPdf()/
 * timesheetPdf(), via the shared resolveBulkItemIds()/participantsFlat() widening) and to
 * Chest Numbers' print()/csv() (FestChestNumberController).
 */
class FestBulkAttendanceTimesheetTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: list<FestEventItem>} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Attendance Sahodaya',
            'domain' => 'bulk-attendance-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BA', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Bulk Attendance School', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Attendance Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Phase 1', 'code' => 'P1', 'sort_order' => 1]);
        $areaA = FestCompetitionArea::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Area A']);

        $items = [
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item One', 'participant_type' => 'individual', 'is_enabled' => true, 'phase_id' => $phase1->id, 'area_id' => $areaA->id]),
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item Two', 'participant_type' => 'individual', 'is_enabled' => true]),
        ];

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '9']);
        foreach ($items as $i => $item) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'admission_number' => "BA{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer', 'chest_no' => $i + 1]);
        }

        return [$sahodaya, $event, $admin, $items];
    }

    public function test_attendance_sheet_item_ids_filters_to_only_the_selected_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/attendance-sheet?item_ids={$items[0]->id}&preview=1");

        $response->assertOk();
        $response->assertSee('Item One');
        $response->assertDontSee('Item Two');
    }

    public function test_timesheet_phase_id_filters_to_only_that_phases_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        $phaseId = $items[0]->phase_id;

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/timesheet?phase_id={$phaseId}&preview=1");

        $response->assertOk();
        $response->assertSee('Item One');
        $response->assertDontSee('Item Two');
    }

    public function test_chest_numbers_csv_area_id_filters_to_only_that_areas_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        $areaId = $items[0]->area_id;

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/chest-numbers/csv?area_id={$areaId}");

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Item One', $csv);
        $this->assertStringNotContainsString('Item Two', $csv);
    }

    /** Existing single item_id / whole-event behavior on these three endpoints is unaffected. */
    public function test_existing_single_item_and_whole_event_behavior_is_unchanged(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();

        $single = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/export/attendance-sheet?item_id={$items[0]->id}&preview=1");
        $single->assertOk();
        $single->assertSee('Item One');
        $single->assertDontSee('Item Two');

        $whole = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/chest-numbers/csv");
        $whole->assertOk();
        $csv = $whole->streamedContent();
        $this->assertStringContainsString('Item One', $csv);
        $this->assertStringContainsString('Item Two', $csv);
    }
}

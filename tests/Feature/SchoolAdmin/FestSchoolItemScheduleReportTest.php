<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for a real bug: the School Admin item-schedule report's Vue
 * template read row.item_title/row.date/row.time, but FestItemScheduleService::
 * rowFromItem() actually emits title/scheduled_date/scheduled_time — every item,
 * date, and time cell rendered blank.
 *
 * IMPORTANT CAVEAT, confirmed empirically while writing this test: Inertia embeds
 * the full `rows` prop as JSON in the initial HTML response regardless of what the
 * Vue <template> does with it, so a plain `assertSee()`/`assertDontSee()` on the
 * HTTP response passes identically whether or not the template binds the right
 * field names — it can only prove the *backend* sends correctly-named/valued props,
 * never that the Vue template actually displays them. Catching a template-binding
 * mismatch like the one this test is named after requires a real browser-rendering
 * test (e.g. Playwright, already configured in this repo's package.json) — this
 * PHPUnit test intentionally locks down the backend half of the contract only.
 */
class FestSchoolItemScheduleReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_schedule_report_sends_title_scheduled_date_and_time_fields(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Item Schedule Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'IS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Item Schedule School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id]);
        $admin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Item Schedule Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Distinctive Mono Act Item', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $venue = FestVenue::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Distinctive Main Auditorium']);
        $stage = FestStage::create(['event_id' => $event->id, 'venue_id' => $venue->id, 'name' => 'Distinctive Stage A']);

        // 'stage' is a separate free-text column from 'stage_id' (the FestStage FK, used
        // to resolve 'venue' below) — rowFromItem() reads both independently, so both are
        // set here to exercise each of the two distinct row fields the Vue template shows.
        FestSchedule::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'stage_id' => $stage->id,
            'stage' => 'Distinctive Stage A', 'scheduled_at' => Carbon::create(2026, 9, 15, 14, 30),
        ]);

        // Clears SchoolDocumentDownloadGateService::membershipFeeCleared(), a gate several
        // school-admin report/download routes check before anything else (see
        // SchoolReportsHideChestNumberTest for the same setup step).
        Registration::create([
            'school_id' => $school->id,
            'academic_year' => AcademicYear::forSahodaya($sahodaya->id),
            'registration_status' => 'completed',
        ]);

        $class = \App\Models\SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 5', 'is_active' => true]);
        $student = \App\Models\Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Schedule Student', 'reg_no' => 'STU/26/12345', 'status' => 'active',
        ]);
        $registration = \App\Models\FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        \App\Models\FestParticipant::create([
            'event_id' => $event->id, 'registration_id' => $registration->id,
            'student_id' => $student->id, 'level_registration_number' => 'FEST-001',
        ]);
        $otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Other School',
            'parent_id' => $sahodaya->id, 'is_active' => true,
        ]);
        $otherClass = \App\Models\SchoolClass::create(['tenant_id' => $otherSchool->id, 'name' => 'Class 5', 'is_active' => true]);
        $otherStudent = \App\Models\Student::create([
            'tenant_id' => $otherSchool->id, 'school_class_id' => $otherClass->id, 'name' => 'Private Other Student', 'reg_no' => 'PRIVATE-123', 'status' => 'active',
        ]);
        $otherRegistration = \App\Models\FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $otherSchool->id, 'status' => 'approved',
        ]);
        \App\Models\FestParticipant::create([
            'event_id' => $event->id, 'registration_id' => $otherRegistration->id,
            'student_id' => $otherStudent->id, 'level_registration_number' => 'PRIVATE-FEST',
        ]);

        $response = $this->actingAs($admin)->get(
            route('school.kalotsav.reports.item-schedule', ['tenantId' => $school->id, 'event' => $event->id]),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('School/Events/ReportItemSchedule', false)
            ->where('rows.0.title', 'Distinctive Mono Act Item')
            ->where('rows.0.scheduled_date', '2026-09-15')
            ->where('rows.0.scheduled_time', '14:30')
            ->where('rows.0.stage', 'Distinctive Stage A')
            ->where('rows.0.venue', 'Distinctive Main Auditorium')
            ->has('rows.0.participants', 1)
            ->where('rows.0.participants.0.name', 'Schedule Student')
            ->where('rows.0.participants.0.fest_id', 'FEST-001')
            ->where('rows.0.participants.0.reg_no', 'STU/26/12345')
            ->missing('rows.0.item_title')
            ->missing('rows.0.date')
            ->missing('rows.0.time')
        );

        $reports = new \App\Services\Events\FestReportService($event);
        $request = \Illuminate\Http\Request::create('/', 'GET', ['school_id' => $school->id, 'date' => '2026-09-15']);
        $download = $reports->export('item-schedule', $request);
        ob_start();
        $download->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Participants,Fest ID,Register Number', $csv);
        $this->assertStringContainsString('FEST-001', $csv);
        $this->assertStringContainsString('STU/26/12345', $csv);
        $this->assertStringNotContainsString('PRIVATE-', $csv);
        $html = view('fest.reports.item-schedule', [
            'event' => $event, 'date' => null, 'rows' => $reports->itemScheduleRows(schoolId: $school->id),
            'summary' => $reports->itemScheduleSummary(),
        ])->render();
        $this->assertStringContainsString('FEST-001', $html);
        $this->assertStringContainsString('STU/26/12345', $html);
        $this->assertStringNotContainsString('PRIVATE-', $html);
        $this->assertSame([], $reports->itemScheduleRows(date: '2026-09-16', schoolId: $school->id));
    }
}

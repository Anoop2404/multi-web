<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestClassCategoryScheme;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The old Item-wise report made an admin pick one item at a time and had no
 * category/phase/region columns at all. This covers the 2026-08-25 rework: one
 * combined table across the whole phased_regional_billing hub, phase/region resolved
 * from the registration's own operational event (never the item's own phase_id — see
 * FestEventReportAnalyticsService::itemWiseReportRows() docblock for why), with
 * category label and school name on every row.
 */
class FestItemWiseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_item_wise_report_shows_category_phase_region_and_school_across_the_hub(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Item Wise Report Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'IW',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Item Wise Report School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Item Wise Report Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'PHASE 1', 'code' => 'P1', 'sort_order' => 1, 'is_regional' => false]);
        $phase2 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'PHASE 2', 'code' => 'P2', 'sort_order' => 2, 'is_regional' => false]);

        $phase1Leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Item Wise Report Kalotsav — PHASE 1', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase1->id, 'partition_role' => 'phase',
        ]);
        $phase2Leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Item Wise Report Kalotsav — PHASE 2', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase2->id, 'partition_role' => 'phase',
        ]);

        $musicItem = FestEventItem::create([
            'event_id' => $phase1Leaf->id, 'title' => 'Light Music-Malayalam', 'item_code' => '104',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music',
            'class_group' => 'lp', 'is_enabled' => true,
        ]);
        $danceItem = FestEventItem::create([
            'event_id' => $phase2Leaf->id, 'title' => 'Bharatanatyam', 'item_code' => '215',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'dance',
            'class_group' => 'up', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Test Student', 'reg_no' => 'STU/1']);

        $musicReg = FestRegistration::create(['event_id' => $phase1Leaf->id, 'item_id' => $musicItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $musicReg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'event_id' => $phase1Leaf->id]);

        $danceReg = FestRegistration::create(['event_id' => $phase2Leaf->id, 'item_id' => $danceItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $danceReg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'event_id' => $phase2Leaf->id]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $hub->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.reports.item-wise', [
            'tenantId' => $sahodaya->id,
            'event' => $hub->id,
        ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $rows = collect($props['rows']);

        $this->assertCount(2, $rows, 'both phases\' registrations must appear in the combined report');

        // "Category" here means class category (item.class_group), not the item's arts
        // genre — see FestEventReportAnalyticsService::itemWiseReportRows().
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $hub);

        $musicRow = $rows->firstWhere('item_id', $musicItem->id);
        $this->assertSame($classGroupLabels['lp'], $musicRow['category_label']);
        $this->assertSame('PHASE 1', $musicRow['phase_name']);
        $this->assertSame('Item Wise Report School', $musicRow['school_name']);
        $this->assertSame('Test Student', $musicRow['participant']);

        $danceRow = $rows->firstWhere('item_id', $danceItem->id);
        $this->assertSame($classGroupLabels['up'], $danceRow['category_label']);
        $this->assertSame('PHASE 2', $danceRow['phase_name']);
        $this->assertSame('Item Wise Report School', $danceRow['school_name']);

        $categories = collect($props['categories'])->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['lp', 'up'], $categories);

        $pdfResponse = $this->actingAs($admin)->get(route('sahodaya.events.reports.item-wise.pdf', [
            'tenantId' => $sahodaya->id,
            'event' => $hub->id,
            'for_whom' => 'District Committee',
        ]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    public function test_school_item_wise_report_is_scoped_to_own_school_and_shows_school_name(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'School Item Wise Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'SW',
            'student_data_mode' => 'counts_only',
        ]);

        $ourSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Our School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $otherSchool = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Other School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'School Item Wise Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation-Malayalam', 'item_code' => '101',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music',
            'class_group' => 'hs', 'is_enabled' => true,
        ]);

        $ourClass = SchoolClass::create(['tenant_id' => $ourSchool->id, 'name' => '10']);
        $otherClass = SchoolClass::create(['tenant_id' => $otherSchool->id, 'name' => '10']);
        $ourStudent = Student::create(['tenant_id' => $ourSchool->id, 'school_class_id' => $ourClass->id, 'name' => 'Our Student', 'reg_no' => 'STU/OWN']);
        $otherStudent = Student::create(['tenant_id' => $otherSchool->id, 'school_class_id' => $otherClass->id, 'name' => 'Other Student', 'reg_no' => 'STU/OTH']);

        $ourReg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $ourSchool->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $ourReg->id, 'student_id' => $ourStudent->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $otherReg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $otherSchool->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $otherReg->id, 'student_id' => $otherStudent->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $ourSchool->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.reports.item-wise', [
            'tenantId' => $ourSchool->id,
            'event' => $event->id,
        ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $rows = collect($props['rows']);

        $this->assertCount(1, $rows, 'must only include the acting school\'s own registrations');
        $this->assertSame('Our Student', $rows->first()['participant']);
        $this->assertSame('Our School', $rows->first()['school_name']);
        $this->assertSame(\App\Support\FestClassGroupScheme::labels(null, $event)['hs'], $rows->first()['category_label']);

        $pdfResponse = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.reports.item-wise.marks-pdf', [
            'tenantId' => $ourSchool->id,
            'event' => $event->id,
        ]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    /**
     * Older/imported items can carry a free-form class_group string (e.g. "category_1")
     * instead of the canonical lp/up/hs/hss/open keys the scheme's own label map is keyed
     * by — without canonicalizing first, the report fell through to a bare capitalized raw
     * key ("Category_1") instead of the scheme's real "Category 1 — Classes ..." label.
     */
    public function test_item_wise_report_canonicalizes_a_raw_class_group_value_to_the_real_scheme_label(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Raw Class Group Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RC',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Raw Class Group School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Raw Class Group Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Anchoring', 'item_code' => '429',
            'stage_type' => 'on_stage', 'participant_type' => 'individual',
            'class_group' => 'category_1', 'is_enabled' => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Test Student', 'reg_no' => 'STU/1']);

        $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.reports.item-wise', [
            'tenantId' => $school->id,
            'event' => $event->id,
        ]));
        $response->assertOk();

        $row = collect($response->viewData('page')['props']['rows'])->first();
        $expectedLabel = \App\Support\FestClassGroupScheme::labels(null, $event)['lp'];

        $this->assertSame($expectedLabel, $row['category_label']);
        $this->assertNotSame('Category_1', $row['category_label'], 'must resolve the real scheme label, not a bare capitalized raw key');
    }

    /**
     * A Sahodaya using a named/numeric class category scheme (e.g. "State Kalotsav") keys
     * its own groups by whatever raw string it was configured with — here literally
     * "category_1" — and that scheme is configured on the ROOT event's fee_settings, not
     * on each individual phase-leaf child event. Registering against the leaf (the normal
     * flow — items live on the operational phase leaf, not the hub) must still resolve the
     * real Sahodaya-configured label ("Category 1 — Classes 3 & 4"), not fall back to the
     * platform default scheme (which doesn't have a "category_1" key at all) or a bare
     * capitalized raw key.
     */
    public function test_item_wise_report_resolves_the_root_events_named_scheme_from_a_phase_leaf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Named Scheme Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'NS',
            'student_data_mode' => 'counts_only',
        ]);

        FestClassCategoryScheme::ensureDefaultsForTenant($sahodaya->id);
        $scheme = FestClassCategoryScheme::forTenant($sahodaya->id)->where('name', 'like', '%State Kalotsav%')->firstOrFail();

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Named Scheme School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Named Scheme Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'fee_settings' => ['class_group_scheme' => (string) $scheme->id],
        ]);
        $phase = FestEventPhase::create(['event_id' => $root->id, 'name' => 'PHASE 2', 'code' => 'P2', 'sort_order' => 1, 'is_regional' => false]);
        $leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Named Scheme Kalotsav — PHASE 2', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $root->id, 'source_phase_id' => $phase->id, 'partition_role' => 'phase',
        ]);

        $item = FestEventItem::create([
            'event_id' => $leaf->id, 'title' => 'Anchoring', 'item_code' => '429',
            'stage_type' => 'on_stage', 'participant_type' => 'individual',
            'class_group' => 'category_1', 'is_enabled' => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Test Student', 'reg_no' => 'STU/1']);

        $reg = FestRegistration::create(['event_id' => $leaf->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'event_id' => $leaf->id]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        // Hitting the report against the LEAF event id, matching the real production URL
        // shape (/kalotsav/reports/{leafEventId}/item-wise) that surfaced this bug.
        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.reports.item-wise', [
            'tenantId' => $school->id,
            'event' => $leaf->id,
        ]));
        $response->assertOk();

        $row = collect($response->viewData('page')['props']['rows'])->first();

        $this->assertSame('Category 1 — Classes 3 & 4', $row['category_label']);
    }
}

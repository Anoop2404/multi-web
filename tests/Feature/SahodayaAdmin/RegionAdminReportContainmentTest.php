<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\SchoolRegionAssignment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Two-region sentinel fixture + failing/passing security tests for
 * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md Phase 1 (§14 first slice):
 * region-admin parent containment (G1) and Registration Register browser/export
 * scope parity with active-year region validation (G3, G5).
 *
 * "Sentinel" schools/participants are named after their region so a leak is
 * immediately obvious in an assertion (e.g. a "Region B" name showing up in a
 * Region A admin's response).
 */
class RegionAdminReportContainmentTest extends TestCase
{
    use RefreshDatabase;

    private const ACADEMIC_YEAR = '2025-26';

    /** @return array{sahodaya: Tenant, regionA: Region, regionB: Region, schoolA: Tenant, schoolB: Tenant, hub: FestEvent, childA: FestEvent, childB: FestEvent} */
    private function twoRegionFixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Containment Test Sahodaya',
            'domain' => 'containment-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'CNT',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => self::ACADEMIC_YEAR,
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RGB', 'is_active' => true]);

        $schoolA = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Region A Sentinel School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $schoolB = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Region B Sentinel School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        SchoolRegionAssignment::create([
            'tenant_id' => $sahodaya->id,
            'region_id' => $regionA->id,
            'school_id' => $schoolA->id,
            'academic_year' => self::ACADEMIC_YEAR,
            'source' => 'sahodaya',
        ]);
        SchoolRegionAssignment::create([
            'tenant_id' => $sahodaya->id,
            'region_id' => $regionB->id,
            'school_id' => $schoolB->id,
            'academic_year' => self::ACADEMIC_YEAR,
            'source' => 'sahodaya',
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Containment Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $childA = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region A Leg',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-a',
            'partition_role' => 'region',
            'region_id' => $regionA->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);
        $childB = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region B Leg',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-b',
            'partition_role' => 'region',
            'region_id' => $regionB->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $this->registerSentinelParticipant($schoolA, $childA, 'Region A Sentinel Student');
        $this->registerSentinelParticipant($schoolB, $childB, 'Region B Sentinel Student');

        return compact('sahodaya', 'regionA', 'regionB', 'schoolA', 'schoolB', 'hub', 'childA', 'childB');
    }

    private function registerSentinelParticipant(Tenant $school, FestEvent $event, string $studentName): void
    {
        $class = SchoolClass::create([
            'tenant_id' => $school->id,
            'name' => '10',
            'display_order' => 10,
        ]);

        $student = Student::create([
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => $studentName,
            'status' => 'active',
        ]);

        $registration = FestRegistration::create([
            'event_id' => $event->id,
            'school_id' => $school->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        FestParticipant::create([
            'registration_id' => $registration->id,
            'event_id' => $event->id,
            'student_id' => $student->id,
            'participant_role' => 'performer',
        ]);
    }

    private function regionAdmin(Tenant $sahodaya, FestEvent $scopedEvent, ?Region $region): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('region_admin');

        FestEventStaff::create([
            'event_id' => $scopedEvent->id,
            'user_id' => $admin->id,
            'duty' => 'region_admin',
            'region_id' => $region?->id,
        ]);

        return $admin;
    }

    public function test_region_admin_assigned_on_hub_with_no_region_is_denied_access(): void
    {
        $f = $this->twoRegionFixture();
        // Assigned duty=region_admin directly on the hub, but no region picked yet — G1's
        // exact failure mode: without the fix, matchesRegionScope() grants full hub access
        // here regardless of region_id.
        $admin = $this->regionAdmin($f['sahodaya'], $f['hub'], null);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.registration-register', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
            ]))
            ->assertForbidden();
    }

    public function test_region_admin_scoped_to_hub_sees_only_assigned_region_without_explicit_filter(): void
    {
        $f = $this->twoRegionFixture();
        $admin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.registration-register', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/RegistrationRegister', false)
                ->where('totals.schools', 1)
                ->where('schoolSummaries.0.school_name', 'REGION A SENTINEL SCHOOL')
                ->where('rows.0.participant_name', 'Region A Sentinel Student')
                ->where('rows', fn ($rows) => collect($rows)->pluck('participant_name')->doesntContain('Region B Sentinel Student')));
    }

    public function test_region_admin_cannot_view_other_region_via_tampered_region_id_param(): void
    {
        $f = $this->twoRegionFixture();
        $admin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.registration-register', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
                'region_id' => $f['regionB']->id,
            ]))
            ->assertForbidden();
    }

    public function test_region_admin_cannot_view_other_regions_school_via_tampered_school_id_param(): void
    {
        $f = $this->twoRegionFixture();
        $admin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.registration-register', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
                'school_id' => $f['schoolB']->id,
            ]))
            ->assertForbidden();
    }

    public function test_region_admin_export_matches_browser_scope_and_excludes_other_region(): void
    {
        $f = $this->twoRegionFixture();
        $admin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $response = $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.registration-register.export', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
            ]))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Region A Sentinel Student', $csv);
        $this->assertStringNotContainsString('Region B Sentinel Student', $csv);
        $this->assertStringContainsString('Region A Sentinel School', $csv);
        $this->assertStringNotContainsString('Region B Sentinel School', $csv);
    }

    public function test_full_sahodaya_admin_sees_combined_data_across_both_regions(): void
    {
        $f = $this->twoRegionFixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.registration-register', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/RegistrationRegister', false)
                ->where('totals.schools', 2)
                ->where('rows', fn ($rows) => collect($rows)->pluck('participant_name')->sort()->values()->all() === [
                    'Region A Sentinel Student',
                    'Region B Sentinel Student',
                ]));
    }

    public function test_region_admin_assigned_directly_on_region_child_can_open_it(): void
    {
        $f = $this->twoRegionFixture();
        // Assigned directly on the leaf/child event (not the hub) — this is the "genuine
        // leaf scope" branch of matchesRegionScope() and must remain unaffected by the G1
        // fix, which only tightens the hub-scope branch.
        $admin = $this->regionAdmin($f['sahodaya'], $f['childA'], $f['regionA']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.registration-register', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childA']->id,
            ]))
            ->assertOk();
    }

    /**
     * Proves ResolveRegionScopedReportEvent (Phase 1 completion) works across the whole
     * reports route group, not just Registration Register: a hub-scoped region admin
     * opening the Reports Hub — a page never touched by hand for region scoping — is
     * transparently resolved to their own regional child before the controller runs.
     */
    public function test_region_admin_hitting_reports_hub_on_hub_is_transparently_resolved_to_their_region_child(): void
    {
        $f = $this->twoRegionFixture();
        $admin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.index', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/Hub', false)
                ->where('event.id', $f['childA']->id));
    }

    public function test_region_participation_report_does_not_reexpand_child_scope_to_siblings(): void
    {
        $f = $this->twoRegionFixture();
        $admin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->actingAs($admin)
            ->get(route('sahodaya.events.reports.participation-counts', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/ParticipationCounts', false)
                ->where('event.id', $f['childA']->id)
                ->where('used.total', 1));
    }
}

<?php

namespace Tests\Feature\Middleware;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventStaff;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PERM-04 residual-risk regression (functional audit, 2026-08-11/12): the web
 * (EnsureSahodayaAdmin) and API (EnsureSahodayaAdminApi) middleware used to each
 * hand-maintain an independent copy of the event/region-admin scoping logic —
 * an edit to one without the other could silently reintroduce drift (this had
 * already happened once: the gap-G1 fix had to be hand-applied to both files
 * separately). Both middleware now delegate to the shared
 * App\Support\EventRegionAdminScope. This test proves the web route
 * (sahodaya.events.show) and the API route (GET .../events/{event}) produce
 * identical allow/deny decisions for the same region-admin/event-admin fixtures.
 */
class RegionScopedAccessParityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, regionA: Region, regionB: Region, hub: FestEvent, childA: FestEvent, childB: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Parity Test Sahodaya',
            'domain' => 'parity-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'PRT',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'PRA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'PRB', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Parity Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $childA = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Parity Region A Leg',
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
            'title' => 'Parity Region B Leg',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-b',
            'partition_role' => 'region',
            'region_id' => $regionB->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        return compact('sahodaya', 'regionA', 'regionB', 'hub', 'childA', 'childB');
    }

    private function regionAdmin(Tenant $sahodaya, FestEvent $scopedEvent, ?Region $region, ?int $sourcePhaseId = null): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('region_admin');
        // Matches how the admin UI actually provisions this role — without it, the
        // dashboard route under test 403s before ever reaching the scope-parity logic
        // this file exists to check.
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('region_admin'));

        FestEventStaff::create([
            'event_id' => $scopedEvent->id,
            'user_id' => $admin->id,
            'duty' => 'region_admin',
            'region_id' => $region?->id,
            'source_phase_id' => $sourcePhaseId,
        ]);

        return $admin;
    }

    /**
     * Extends fixture() with two named phases on the hub and two region-A leaf events,
     * one tagged to each phase — the shape phased_regional_billing topology actually
     * produces (see FestReportScopeResolver::regionEventIdsForRoot()). A separate method
     * rather than folding into fixture() itself, so the five pre-existing tests above
     * are untouched and don't pay for rows they don't need.
     *
     * @return array{sahodaya: Tenant, regionA: Region, regionB: Region, hub: FestEvent, childA: FestEvent, childB: FestEvent, phase1: FestEventPhase, phase2: FestEventPhase, childAPhase1: FestEvent, childAPhase2: FestEvent}
     */
    private function phaseFixture(): array
    {
        $f = $this->fixture();

        $phase1 = FestEventPhase::create(['event_id' => $f['hub']->id, 'name' => 'Level 1', 'code' => 'L1', 'sort_order' => 1]);
        $phase2 = FestEventPhase::create(['event_id' => $f['hub']->id, 'name' => 'Level 2', 'code' => 'L2', 'sort_order' => 2]);

        $childAPhase1 = FestEvent::create([
            'tenant_id' => $f['sahodaya']->id,
            'title' => 'Parity Region A Leg — Level 1',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $f['hub']->id,
            'partition_key' => 'region-a-phase-1',
            'partition_role' => 'region',
            'region_id' => $f['regionA']->id,
            'source_phase_id' => $phase1->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $childAPhase2 = FestEvent::create([
            'tenant_id' => $f['sahodaya']->id,
            'title' => 'Parity Region A Leg — Level 2',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $f['hub']->id,
            'partition_key' => 'region-a-phase-2',
            'partition_role' => 'region',
            'region_id' => $f['regionA']->id,
            'source_phase_id' => $phase2->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        // Region B's phase-1 leg — only needed by the phase_admin tests below, to prove a
        // phase_admin scope reaches every region's leaf under its phase (not just one
        // region, the way region_admin's scope does). Existing tests above only reference
        // the region-A legs, so this addition doesn't change their behavior.
        $childBPhase1 = FestEvent::create([
            'tenant_id' => $f['sahodaya']->id,
            'title' => 'Parity Region B Leg — Level 1',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $f['hub']->id,
            'partition_key' => 'region-b-phase-1',
            'partition_role' => 'region',
            'region_id' => $f['regionB']->id,
            'source_phase_id' => $phase1->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        return [...$f, 'phase1' => $phase1, 'phase2' => $phase2, 'childAPhase1' => $childAPhase1, 'childAPhase2' => $childAPhase2, 'childBPhase1' => $childBPhase1];
    }

    private function phaseAdmin(Tenant $sahodaya, FestEvent $scopedEvent, ?int $sourcePhaseId): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('phase_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('phase_admin'));

        FestEventStaff::create([
            'event_id' => $scopedEvent->id,
            'user_id' => $admin->id,
            'duty' => 'phase_admin',
            'region_id' => null,
            'source_phase_id' => $sourcePhaseId,
        ]);

        return $admin;
    }

    private function eventAdmin(Tenant $sahodaya, FestEvent $scopedEvent): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));

        FestEventStaff::create([
            'event_id' => $scopedEvent->id,
            'user_id' => $admin->id,
            'duty' => 'event_admin',
        ]);

        return $admin;
    }

    /** Web: sahodaya.events.show. Requires an authenticated session user. */
    private function webResult(User $admin, Tenant $sahodaya, FestEvent $event): int
    {
        return $this->actingAs($admin)
            ->get(route('sahodaya.events.show', [
                'tenantId' => $sahodaya->id,
                'event' => $event->id,
            ]))
            ->getStatusCode();
    }

    /** API: GET /api/v1/sahodaya/{tenantId}/events/{event}. Uses a Sanctum bearer token, matching the API's own auth style. */
    private function apiResult(User $admin, Tenant $sahodaya, FestEvent $event): int
    {
        $token = $admin->createToken('parity-test')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/sahodaya/{$sahodaya->id}/events/{$event->id}")
            ->getStatusCode();
    }

    public function test_region_admin_on_hub_with_no_region_is_denied_by_both_middleware_on_the_hub(): void
    {
        $f = $this->fixture();
        // Same fixture, but each middleware needs its own admin/token since a session
        // user and a Sanctum token aren't interchangeable across the two guards.
        $webAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], null);
        $apiAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], null);

        $this->assertSame(403, $this->webResult($webAdmin, $f['sahodaya'], $f['hub']));
        $this->assertSame(403, $this->apiResult($apiAdmin, $f['sahodaya'], $f['hub']));
    }

    public function test_region_admin_scoped_on_hub_can_reach_own_regions_child_via_both_middleware(): void
    {
        $f = $this->fixture();
        $webAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);
        $apiAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childA']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childA']));
    }

    public function test_region_admin_scoped_on_hub_is_denied_the_other_regions_child_via_both_middleware(): void
    {
        $f = $this->fixture();
        $webAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);
        $apiAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->assertSame(403, $this->webResult($webAdmin, $f['sahodaya'], $f['childB']));
        $this->assertSame(403, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childB']));
    }

    public function test_region_admin_assigned_directly_on_a_child_can_open_it_via_both_middleware(): void
    {
        $f = $this->fixture();
        $webAdmin = $this->regionAdmin($f['sahodaya'], $f['childA'], $f['regionA']);
        $apiAdmin = $this->regionAdmin($f['sahodaya'], $f['childA'], $f['regionA']);

        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childA']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childA']));
    }

    public function test_event_admin_scoped_to_one_event_is_denied_a_different_event_via_both_middleware(): void
    {
        $f = $this->fixture();
        $webAdmin = $this->eventAdmin($f['sahodaya'], $f['childA']);
        $apiAdmin = $this->eventAdmin($f['sahodaya'], $f['childA']);

        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childA']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childA']));

        $this->assertSame(403, $this->webResult($webAdmin, $f['sahodaya'], $f['childB']));
        $this->assertSame(403, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childB']));
    }

    public function test_region_admin_scoped_to_one_phase_can_reach_matching_phase_child_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA'], $f['phase1']->id);
        $apiAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA'], $f['phase1']->id);

        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase1']));
    }

    public function test_region_admin_scoped_to_one_phase_is_denied_a_different_phase_child_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA'], $f['phase1']->id);
        $apiAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA'], $f['phase1']->id);

        $this->assertSame(403, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase2']));
        $this->assertSame(403, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase2']));
    }

    /**
     * Regression guard for the fix itself: a region_admin who left "All phases in
     * region" selected (source_phase_id null — the default, and the shape of every
     * assignment that existed before this field did) must keep reaching every phase
     * of their region, exactly as before this change.
     */
    public function test_region_admin_with_no_phase_restriction_can_reach_every_phase_child_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);
        $apiAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA']);

        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase2']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase2']));
    }

    /**
     * The defining difference from region_admin: a phase_admin scoped on the hub reaches
     * their phase's leaf event in EVERY region, not just one.
     */
    public function test_phase_admin_scoped_on_hub_can_reach_matching_phase_child_in_any_region_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webAdmin = $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id);
        $apiAdmin = $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id);

        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childBPhase1']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childBPhase1']));
    }

    public function test_phase_admin_scoped_on_hub_is_denied_a_different_phase_child_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webAdmin = $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id);
        $apiAdmin = $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id);

        $this->assertSame(403, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase2']));
        $this->assertSame(403, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase2']));
    }

    public function test_phase_admin_assigned_directly_on_a_child_can_open_it_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webAdmin = $this->phaseAdmin($f['sahodaya'], $f['childAPhase1'], $f['phase1']->id);
        $apiAdmin = $this->phaseAdmin($f['sahodaya'], $f['childAPhase1'], $f['phase1']->id);

        $this->assertSame(200, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(200, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase1']));
    }

    /**
     * Regression guard for resolve()'s whereNotNull('source_phase_id') filter: unlike
     * region_admin (where an unscoped/null value is valid — "every phase"), a phase_admin
     * row is only ever created with a real phase (FestEventStaffController::store()
     * requires one). A row that somehow has a null phase anyway — bad data, or a future
     * regression in that requirement — must fail closed rather than being read as
     * unscoped access to everything under the hub.
     */
    public function test_phase_admin_with_no_phase_set_is_denied_everything_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webAdmin = $this->phaseAdmin($f['sahodaya'], $f['hub'], null);
        $apiAdmin = $this->phaseAdmin($f['sahodaya'], $f['hub'], null);

        $this->assertSame(403, $this->webResult($webAdmin, $f['sahodaya'], $f['hub']));
        $this->assertSame(403, $this->apiResult($apiAdmin, $f['sahodaya'], $f['hub']));
        $this->assertSame(403, $this->webResult($webAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(403, $this->apiResult($apiAdmin, $f['sahodaya'], $f['childAPhase1']));
    }

    public function test_region_admin_and_phase_admin_do_not_leak_into_each_others_scope_via_both_middleware(): void
    {
        $f = $this->phaseFixture();
        $webRegionAdmin = $this->regionAdmin($f['sahodaya'], $f['hub'], $f['regionA'], $f['phase1']->id);
        $webPhaseAdmin = $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id);

        // Region A + phase 1 admin: denied region B's phase-1 leg, even though it's the
        // same phase — region_admin's scope is still region-locked.
        $this->assertSame(403, $this->webResult($webRegionAdmin, $f['sahodaya'], $f['childBPhase1']));

        // Phase 1 admin (no region): reaches both regions' phase-1 legs.
        $this->assertSame(200, $this->webResult($webPhaseAdmin, $f['sahodaya'], $f['childAPhase1']));
        $this->assertSame(200, $this->webResult($webPhaseAdmin, $f['sahodaya'], $f['childBPhase1']));
    }
}

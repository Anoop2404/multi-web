<?php

namespace Tests\Feature\Middleware;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
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

    private function eventAdmin(Tenant $sahodaya, FestEvent $scopedEvent): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('event_admin');

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
}

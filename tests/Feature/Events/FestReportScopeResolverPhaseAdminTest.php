<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestEventStaff;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\Reports\FestReportScopeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers FestReportScopeResolver's phase_admin extension (Layer 2 of the standalone
 * phase_admin role): a region-less scope should aggregate every region's leaf under the
 * assigned phase — reusing combinedScope()'s existing phased_regional_billing branch,
 * the same mechanism region_admin+phase already relies on for its own phase filtering.
 */
class FestReportScopeResolverPhaseAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, hub: FestEvent, phase1: FestEventPhase, phase2: FestEventPhase, childAPhase1: FestEvent, childBPhase1: FestEvent, childAPhase2: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Report Scope Phase Admin Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RSPA', 'student_data_mode' => 'counts_only']);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RSA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RSB', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Report Scope Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Level 1', 'code' => 'L1', 'sort_order' => 1, 'is_regional' => true]);
        $phase2 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Level 2', 'code' => 'L2', 'sort_order' => 2, 'is_regional' => true]);

        $childAPhase1 = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A — Level 1', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a-phase-1', 'partition_role' => 'region',
            'region_id' => $regionA->id, 'source_phase_id' => $phase1->id, 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $childBPhase1 = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region B — Level 1', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-b-phase-1', 'partition_role' => 'region',
            'region_id' => $regionB->id, 'source_phase_id' => $phase1->id, 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $childAPhase2 = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A — Level 2', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a-phase-2', 'partition_role' => 'region',
            'region_id' => $regionA->id, 'source_phase_id' => $phase2->id, 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);

        return compact('sahodaya', 'admin', 'hub', 'phase1', 'phase2', 'childAPhase1', 'childBPhase1', 'childAPhase2');
    }

    private function phaseAdmin(Tenant $sahodaya, FestEvent $scopedEvent, ?int $sourcePhaseId, ?User $user = null): User
    {
        $admin = $user ?? User::factory()->create(['tenant_id' => $sahodaya->id]);
        if (! $admin->hasRole('phase_admin')) {
            $admin->assignRole('phase_admin');
        }

        FestEventStaff::create([
            'event_id' => $scopedEvent->id,
            'user_id' => $admin->id,
            'duty' => 'phase_admin',
            'region_id' => null,
            'source_phase_id' => $sourcePhaseId,
        ]);

        return $admin;
    }

    public function test_phase_admin_scope_aggregates_every_region_under_the_assigned_phase(): void
    {
        $f = $this->fixture();
        $admin = $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id);

        $scope = app(FestReportScopeResolver::class)->resolve($f['hub'], $admin);

        $this->assertEqualsCanonicalizing([$f['childAPhase1']->id, $f['childBPhase1']->id], $scope->eventIds);
        $this->assertNotContains($f['childAPhase2']->id, $scope->eventIds);
        $this->assertSame($f['phase1']->id, $scope->competitionPhaseId);
        $this->assertNull($scope->regionId);
        $this->assertTrue($scope->isActorRestricted);
    }

    public function test_phase_admin_scope_ignores_a_different_phase_even_when_requested(): void
    {
        $f = $this->fixture();
        $admin = $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id);

        try {
            app(FestReportScopeResolver::class)->resolve($f['hub'], $admin, ['competition_phase_id' => $f['phase2']->id]);
            $this->fail('Expected a 403 HttpException.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_phase_admin_with_multiple_assigned_phases_must_disambiguate(): void
    {
        $f = $this->fixture();
        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase1']->id, $admin);
        $this->phaseAdmin($f['sahodaya'], $f['hub'], $f['phase2']->id, $admin);

        $ambiguous = app(FestReportScopeResolver::class)->resolve($f['hub'], $admin);
        $this->assertSame([], $ambiguous->eventIds);

        $disambiguated = app(FestReportScopeResolver::class)->resolve($f['hub'], $admin, ['competition_phase_id' => $f['phase2']->id]);
        $this->assertSame([$f['childAPhase2']->id], $disambiguated->eventIds);
    }

    public function test_phase_admin_with_no_phase_set_gets_an_empty_scope(): void
    {
        $f = $this->fixture();
        $admin = $this->phaseAdmin($f['sahodaya'], $f['hub'], null);

        $scope = app(FestReportScopeResolver::class)->resolve($f['hub'], $admin);
        $this->assertSame([], $scope->eventIds);
    }

    public function test_region_admin_and_phase_admin_scopes_stay_independent(): void
    {
        $f = $this->fixture();
        $regionAdmin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $regionAdmin->assignRole('region_admin');
        FestEventStaff::create([
            'event_id' => $f['hub']->id,
            'user_id' => $regionAdmin->id,
            'duty' => 'region_admin',
            'region_id' => Region::where('tenant_id', $f['sahodaya']->id)->where('name', 'Region A')->value('id'),
            'source_phase_id' => $f['phase1']->id,
        ]);

        $regionScope = app(FestReportScopeResolver::class)->resolve($f['hub'], $regionAdmin);
        $this->assertSame([$f['childAPhase1']->id], $regionScope->eventIds);
        $this->assertNotContains($f['childBPhase1']->id, $regionScope->eventIds);
    }
}

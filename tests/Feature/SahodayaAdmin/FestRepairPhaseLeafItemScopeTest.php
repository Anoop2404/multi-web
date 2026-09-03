<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestRepairPhaseLeafItemScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeHubWithLeaf(string $suffix): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => "Scope Test Sahodaya {$suffix}",
            'domain' => "scope-sahodaya-{$suffix}.test",
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'SCP',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => "Scope School {$suffix}",
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => "Scope Kalotsav {$suffix}",
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'standard',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $phaseA = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Phase A', 'code' => 'PA']);
        $phaseB = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Phase B', 'code' => 'PB']);

        $itemA = FestEventItem::create(['event_id' => $hub->id, 'title' => 'Item A', 'phase_id' => $phaseA->id]);
        $itemB = FestEventItem::create(['event_id' => $hub->id, 'title' => 'Item B', 'phase_id' => $phaseB->id]);
        $itemC = FestEventItem::create(['event_id' => $hub->id, 'title' => 'Item C', 'phase_id' => $phaseB->id]);

        $leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => "Phase A Leaf {$suffix}",
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'source_phase_id' => $phaseA->id,
            'partition_key' => 'pa',
            'partition_role' => 'phase',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        // Correctly scoped -- belongs to Phase A, matches the leaf's own phase.
        $leafItemA = FestEventItem::create([
            'event_id' => $leaf->id,
            'title' => 'Item A',
            'inherited_from_item_id' => $itemA->id,
        ]);

        // Misplaced -- belongs to Phase B, zero registrations. Safe to remove.
        $leafItemB = FestEventItem::create([
            'event_id' => $leaf->id,
            'title' => 'Item B',
            'inherited_from_item_id' => $itemB->id,
        ]);

        // Misplaced -- belongs to Phase B, but has a live registration. Must be skipped.
        $leafItemC = FestEventItem::create([
            'event_id' => $leaf->id,
            'title' => 'Item C',
            'inherited_from_item_id' => $itemC->id,
        ]);

        FestRegistration::create([
            'event_id' => $leaf->id,
            'item_id' => $leafItemC->id,
            'school_id' => $school->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        return compact('sahodaya', 'leafItemA', 'leafItemB', 'leafItemC');
    }

    public function test_dry_run_does_not_mutate_data(): void
    {
        ['sahodaya' => $sahodaya, 'leafItemA' => $leafItemA, 'leafItemB' => $leafItemB, 'leafItemC' => $leafItemC] = $this->makeHubWithLeaf('dry');

        $this->artisan('fest:repair-phase-leaf-item-scope', [
            '--sahodaya' => $sahodaya->id,
        ])->assertExitCode(0);

        $this->assertTrue((bool) $leafItemA->fresh()->is_enabled);
        $this->assertTrue((bool) $leafItemB->fresh()->is_enabled);
        $this->assertTrue((bool) $leafItemC->fresh()->is_enabled);
    }

    public function test_commit_disables_only_misplaced_items_with_no_dependent_data(): void
    {
        ['sahodaya' => $sahodaya, 'leafItemA' => $leafItemA, 'leafItemB' => $leafItemB, 'leafItemC' => $leafItemC] = $this->makeHubWithLeaf('commit');

        $this->artisan('fest:repair-phase-leaf-item-scope', [
            '--sahodaya' => $sahodaya->id,
            '--commit' => true,
        ])->assertExitCode(0);

        // Correctly scoped: untouched. Never deleted, never disabled.
        $this->assertNull($leafItemA->fresh()->deleted_at);
        $this->assertTrue((bool) $leafItemA->fresh()->is_enabled);
        // Misplaced, no dependent data: disabled, not deleted.
        $this->assertNull($leafItemB->fresh()->deleted_at);
        $this->assertFalse((bool) $leafItemB->fresh()->is_enabled);
        // Misplaced, but has a registration: left alone (still enabled).
        $this->assertNull($leafItemC->fresh()->deleted_at);
        $this->assertTrue((bool) $leafItemC->fresh()->is_enabled);
    }
}

<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the phase-plan wizard's two headline features over
 * FestPhasedStructureConfigurator (already covered directly by
 * FestPhasedStructureConfiguratorTest): the dry-run preview surfaced over HTTP, and the
 * unmapped-items gate blocking commit — the CLI already had both, the old one-phase-at-a-
 * time admin UI had neither.
 */
class FestPhasePlanWizardControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent, regionA: Region} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Wizard Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'WZ',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Wizard Test Kalotsav',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'draft',
            'fee_type' => 'none',
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RA', 'is_active' => true]);

        FestEventItem::create(['event_id' => $event->id, 'title' => 'Item One', 'item_code' => 'ITEM1', 'is_enabled' => true]);
        FestEventItem::create(['event_id' => $event->id, 'title' => 'Item Two', 'item_code' => 'ITEM2', 'is_enabled' => true]);

        return compact('sahodaya', 'admin', 'event', 'regionA');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'batches' => [['code' => 'LEVEL_1', 'name' => 'Level 1', 'school_base_fee' => 1000]],
            'phases' => [['code' => 'PHASE_1', 'name' => 'Phase 1', 'batch_code' => 'LEVEL_1', 'is_regional' => false, 'region_codes' => []]],
            'item_phase_map' => ['ITEM1' => 'PHASE_1', 'ITEM2' => 'PHASE_1'],
        ], $overrides);
    }

    public function test_preview_reports_planned_actions_and_unmapped_items_without_writing_anything(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->actingAs($admin)->postJson(
            route('sahodaya.events.phase-plan-wizard.preview', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            $this->payload(['item_phase_map' => ['ITEM1' => 'PHASE_1']]), // ITEM2 deliberately left unmapped
        );

        $response->assertOk();
        $response->assertJsonPath('batches.0.code', 'LEVEL_1');
        $response->assertJsonPath('batches.0.action', 'create');
        $response->assertJsonPath('phases.0.code', 'PHASE_1');
        $response->assertJsonPath('phases.0.item_count', 1);
        $response->assertJsonCount(1, 'unmapped_items');
        $response->assertJsonPath('unmapped_items.0.item_code', 'ITEM2');

        $this->assertSame(0, FestRegistrationBatch::where('event_id', $event->id)->count());
        $this->assertSame(0, FestEventPhase::where('event_id', $event->id)->count());
        $this->assertSame('standard', $event->fresh()->workflow_mode);
    }

    public function test_commit_is_blocked_while_any_enabled_item_is_unmapped(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->actingAs($admin)->post(
            route('sahodaya.events.phase-plan-wizard.commit', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            $this->payload(['item_phase_map' => ['ITEM1' => 'PHASE_1']]),
        );

        $response->assertStatus(422);
        $this->assertSame(0, FestRegistrationBatch::where('event_id', $event->id)->count());
        $this->assertSame('standard', $event->fresh()->workflow_mode);
    }

    public function test_commit_creates_structure_and_activates_phased_regional_billing_when_fully_mapped(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $response = $this->actingAs($admin)->post(
            route('sahodaya.events.phase-plan-wizard.commit', ['tenantId' => $sahodaya->id, 'event' => $event->id]),
            $this->payload(),
        );

        $response->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('phased_regional_billing', $event->workflow_mode);
        $this->assertTrue($event->phase_mode_enabled);

        $batch = FestRegistrationBatch::where('event_id', $event->id)->first();
        $this->assertSame('LEVEL_1', $batch->code);

        $phase = FestEventPhase::where('event_id', $event->id)->first();
        $this->assertSame('PHASE_1', $phase->code);

        $itemsByCode = FestEventItem::where('event_id', $event->id)->get()->keyBy('item_code');
        $this->assertSame($phase->id, $itemsByCode['ITEM1']->phase_id);
        $this->assertSame($phase->id, $itemsByCode['ITEM2']->phase_id);
    }
}

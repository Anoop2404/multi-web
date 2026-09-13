<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Events\PublicOperationalEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for PublicOperationalEventService::directScope()'s 'role' value.
 * It was hardcoded to 'event' unconditionally, which meant PublicFestScoreboardService::
 * scoreboard()'s and FestPortalController::results()'s already-built role === 'phase' /
 * 'overall' routing (the phased-event cumulative-points-across-phases board) was never
 * reachable from any public page — every public controller resolves its scope through
 * this method. See FestPhaseScoreboardService for the routing this unblocks.
 */
class PublicOperationalEventServiceDirectScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_event_for_a_non_phased_event(): void
    {
        $sahodaya = $this->makeSahodaya();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Plain Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $scope = app(PublicOperationalEventService::class)->directScope($event);

        $this->assertSame('event', $scope['role']);
    }

    public function test_role_is_overall_for_the_hub_of_a_phased_event(): void
    {
        [$root] = $this->phasedFixture();

        $scope = app(PublicOperationalEventService::class)->directScope($root);

        $this->assertSame('overall', $scope['role']);
    }

    public function test_role_is_phase_for_a_non_regional_phases_own_leaf(): void
    {
        [$root, , $phases] = $this->phasedFixture();

        $digiLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phases['DIGI']->id)
            ->firstOrFail();

        $scope = app(PublicOperationalEventService::class)->directScope($digiLeaf);

        $this->assertSame('phase', $scope['role']);
        $this->assertSame($phases['DIGI']->id, $scope['source_phase_id']);
    }

    /**
     * A single region's own public page must keep showing just its own results — not
     * silently expand to the whole phase combined with its sibling region — per
     * PublicOperationalEventService's own class docblock ("public pages must not
     * silently ... query its siblings").
     */
    public function test_role_stays_event_for_one_region_of_a_regional_phase(): void
    {
        [$root, $regions, $phases] = $this->phasedFixture();

        $regionLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phases['OFF_STAGE']->id)
            ->where('region_id', $regions['NILAMBUR']->id)
            ->firstOrFail();

        $scope = app(PublicOperationalEventService::class)->directScope($regionLeaf);

        $this->assertSame('event', $scope['role']);
    }

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'DirectScope Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'DS', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    /** @return array{0: FestEvent, 1: array<string, Region>, 2: array<string, FestEventPhase>} */
    private function phasedFixture(): array
    {
        $sahodaya = $this->makeSahodaya();

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'DirectScope Test Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open', 'fee_type' => 'none',
            'fee_settings' => ['fee_model' => 'item_catalog'],
            'workflow_mode' => FestPhasedWorkflowService::MODE, 'phase_mode_enabled' => true, 'conduct_mode' => 'partitioned',
        ]);

        $regions = collect(['NILAMBUR' => 'Nilambur', 'TIRUR' => 'Tirur'])
            ->mapWithKeys(fn (string $name, string $code) => [$code => Region::create([
                'tenant_id' => $sahodaya->id, 'name' => $name, 'code' => $code, 'is_active' => true,
            ])]);

        $batch = FestRegistrationBatch::create([
            'event_id' => $root->id, 'code' => 'LEVEL_1', 'name' => 'Level 1', 'sort_order' => 1,
            'school_base_fee' => 4000, 'status' => 'registration_open', 'registration_close' => now()->addMonth(),
        ]);

        $phaseService = app(FestEventPhaseService::class);
        $workflow = app(FestPhasedWorkflowService::class);
        $definitions = [
            ['code' => 'DIGI', 'name' => 'Digi Fest', 'sort_order' => 1, 'regional' => false, 'regions' => []],
            ['code' => 'OFF_STAGE', 'name' => 'Off Stage', 'sort_order' => 2, 'regional' => true, 'regions' => [$regions['NILAMBUR']->id, $regions['TIRUR']->id]],
        ];

        $phases = collect();
        foreach ($definitions as $d) {
            $phase = $phaseService->createPhase($root, [
                'name' => $d['name'], 'code' => $d['code'], 'sort_order' => $d['sort_order'],
                'registration_batch_id' => $batch->id, 'is_regional' => $d['regional'],
            ]);
            $phase->update(['registration_open' => now()->subDay(), 'registration_close' => now()->addMonth(), 'status' => 'registration_open']);
            if ($d['regions'] !== []) {
                $workflow->syncAllowedRegions($phase, $d['regions']);
            }
            $phases[$d['code']] = $phase->fresh();
        }

        app(FestPhaseTopologyService::class)->sync($root->fresh());

        return [$root->fresh(), $regions->all(), $phases->all()];
    }
}

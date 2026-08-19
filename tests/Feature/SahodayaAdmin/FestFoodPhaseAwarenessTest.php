<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestFoodBill;
use App\Models\FestFoodCoupon;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodOrderItem;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPartitionService;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Events\FestPhasedWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the food module's "phase-wise" system (workflow_mode =
 * 'phased_regional_billing', FestPhaseTopologyService::syncLeaf()) — previously
 * untested anywhere (see tests/Feature/SahodayaAdmin/FestFoodRegionAwarenessTest.php,
 * which only exercises the legacy "region-wise" conduct_mode = 'partitioned' system, and
 * tests/Feature/Events/FestPhasedRegionalBillingWorkflowTest.php, which builds a full
 * phased fixture but never creates a single food record). Food itself has no phase_id
 * anywhere (docs/FOOD_MODULE_AUDIT_2026_08_17.md) — "phase-wise" food is really just
 * "food scoped to a leaf FestEvent spawned per phase (x region)", so this file
 * characterizes what actually happens to menu items, bills, and the cross-leaf rollups
 * once that leaf-spawning mechanism is in play.
 */
class FestFoodPhaseAwarenessTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Phase Awareness Sahodaya',
            'domain' => 'phase-awareness-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'PA',
            'student_data_mode' => 'counts_only',
        ]);

        return $sahodaya;
    }

    /**
     * A phased-workflow root with one non-regional phase (spawns a single "phase-only"
     * leaf, partition_role = 'phase') and one regional phase with a single enabled region
     * (spawns a "phase x region" leaf, partition_role = 'region') — deliberately NOT
     * synced yet, so callers can add hub-level fixture data first.
     *
     * @return array{sahodaya: Tenant, root: FestEvent, region: Region, nonRegionalPhase: FestEventPhase, regionalPhase: FestEventPhase}
     */
    private function makePhasedRoot(): array
    {
        $sahodaya = $this->makeSahodaya();

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Phase Awareness Root',
            'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
        ]);

        $region = Region::create([
            'tenant_id' => $sahodaya->id, 'name' => 'Tirur', 'code' => 'TIR', 'is_active' => true,
        ]);

        $phaseService = app(FestEventPhaseService::class);

        $nonRegionalPhase = $phaseService->createPhase($root, [
            'name' => 'Digi Fest', 'code' => 'DIGI', 'sort_order' => 1, 'is_regional' => false,
        ]);

        $regionalPhase = $phaseService->createPhase($root, [
            'name' => 'Off Stage', 'code' => 'OFF_STAGE', 'sort_order' => 2, 'is_regional' => true,
        ]);
        app(FestPhasedWorkflowService::class)->syncAllowedRegions($regionalPhase, [$region->id]);

        return compact('sahodaya', 'root', 'region', 'nonRegionalPhase', 'regionalPhase');
    }

    /**
     * Runs FestPhaseTopologyService::sync() and resolves the two leaves spawned by
     * makePhasedRoot()'s phases: [phase-only leaf, phase x region leaf].
     *
     * @return array{0: FestEvent, 1: FestEvent}
     */
    private function syncAndGetLeaves(FestEvent $root, FestEventPhase $nonRegionalPhase, FestEventPhase $regionalPhase, Region $region): array
    {
        app(FestPhaseTopologyService::class)->sync($root->fresh());

        $phaseOnlyLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $nonRegionalPhase->id)
            ->whereNull('region_id')
            ->firstOrFail();

        $phaseRegionLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $regionalPhase->id)
            ->where('region_id', $region->id)
            ->firstOrFail();

        return [$phaseOnlyLeaf, $phaseRegionLeaf];
    }

    private function addMenuItem(string $tenantId, int $eventId, string $name, float $price, string $mealType = 'lunch'): FestFoodMenuItem
    {
        return FestFoodMenuItem::create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'menu_date' => '2026-09-01',
            'meal_type' => $mealType,
            'name' => $name,
            'price' => $price,
            'is_available' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_topology_sync_copies_hub_menu_items_onto_phase_only_and_phase_region_leaves(): void
    {
        ['sahodaya' => $sahodaya, 'root' => $root, 'region' => $region, 'nonRegionalPhase' => $nonRegionalPhase, 'regionalPhase' => $regionalPhase] = $this->makePhasedRoot();

        $this->addMenuItem($sahodaya->id, $root->id, 'Veg Meals', 50, 'lunch');
        $this->addMenuItem($sahodaya->id, $root->id, 'Chapati', 30, 'dinner');

        [$phaseOnlyLeaf, $phaseRegionLeaf] = $this->syncAndGetLeaves($root, $nonRegionalPhase, $regionalPhase, $region);

        $this->assertSame('phase', $phaseOnlyLeaf->partition_role);
        $this->assertSame('region', $phaseRegionLeaf->partition_role);

        $this->assertEqualsCanonicalizing(
            ['Veg Meals', 'Chapati'],
            FestFoodMenuItem::where('event_id', $phaseOnlyLeaf->id)->pluck('name')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['Veg Meals', 'Chapati'],
            FestFoodMenuItem::where('event_id', $phaseRegionLeaf->id)->pluck('name')->all()
        );
        $this->assertSame(50.0, (float) FestFoodMenuItem::where('event_id', $phaseOnlyLeaf->id)->where('name', 'Veg Meals')->value('price'));
        $this->assertSame(30.0, (float) FestFoodMenuItem::where('event_id', $phaseRegionLeaf->id)->where('name', 'Chapati')->value('price'));
    }

    /**
     * Documents CURRENT behavior (not fixed here) — Food Module audit 2026-08-17, Finding
     * 5 / §2: FestFoodMenuSyncService::copyMenuItemToPartition() matches an existing leaf
     * item on (menu_date, meal_type, name) only and simply no-ops on a match. It never
     * updates price/description/availability, so a hub price change made after leaves
     * already exist never reaches them, even on a fresh re-sync. Verified directly against
     * current source (app/Services/Events/FestFoodMenuSyncService.php:51-89), not assumed
     * from the audit doc.
     */
    public function test_menu_sync_is_copy_once_and_leaves_a_synced_items_price_stale_after_a_hub_price_change(): void
    {
        ['sahodaya' => $sahodaya, 'root' => $root, 'region' => $region, 'nonRegionalPhase' => $nonRegionalPhase, 'regionalPhase' => $regionalPhase] = $this->makePhasedRoot();

        $hubItem = $this->addMenuItem($sahodaya->id, $root->id, 'Veg Meals', 50, 'lunch');

        [$phaseOnlyLeaf, $phaseRegionLeaf] = $this->syncAndGetLeaves($root, $nonRegionalPhase, $regionalPhase, $region);

        $this->assertSame(50.0, (float) FestFoodMenuItem::where('event_id', $phaseOnlyLeaf->id)->value('price'));
        $this->assertSame(50.0, (float) FestFoodMenuItem::where('event_id', $phaseRegionLeaf->id)->value('price'));

        // Hub price changes AFTER both leaves already have their own copy.
        $hubItem->update(['price' => 75]);

        // Re-running the sync (as an admin would after editing the hub menu) matches the
        // leaf's existing row on (date, meal_type, name) and skips it — see
        // copyMenuItemToPartition()'s $exists check.
        app(FestPhaseTopologyService::class)->sync($root->fresh());

        $this->assertSame(75.0, (float) $hubItem->fresh()->price, 'Sanity check: the hub item itself did update.');
        $this->assertSame(
            50.0,
            (float) FestFoodMenuItem::where('event_id', $phaseOnlyLeaf->id)->value('price'),
            'Phase-only leaf price should still be stale — copyMenuItemToPartition() never updates an already-matched item.'
        );
        $this->assertSame(
            50.0,
            (float) FestFoodMenuItem::where('event_id', $phaseRegionLeaf->id)->value('price'),
            'Phase x region leaf price should still be stale for the same reason.'
        );
        $this->assertSame(1, FestFoodMenuItem::where('event_id', $phaseOnlyLeaf->id)->count(), 'No duplicate row either — the (date, meal_type, name) match still hits.');
        $this->assertSame(1, FestFoodMenuItem::where('event_id', $phaseRegionLeaf->id)->count());
    }

    public function test_bills_order_items_and_coupons_on_separate_phase_leaves_do_not_leak_into_each_other(): void
    {
        ['root' => $root, 'region' => $region, 'nonRegionalPhase' => $nonRegionalPhase, 'regionalPhase' => $regionalPhase, 'sahodaya' => $sahodaya] = $this->makePhasedRoot();
        [$phaseOnlyLeaf, $phaseRegionLeaf] = $this->syncAndGetLeaves($root, $nonRegionalPhase, $regionalPhase, $region);

        $schoolA = (string) Str::uuid();
        $schoolB = (string) Str::uuid();

        $billOnPhaseLeaf = FestFoodBill::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $phaseOnlyLeaf->id, 'school_id' => $schoolA,
            'status' => 'open', 'payment_mode' => 'prepaid', 'amount_total' => 0, 'amount_paid' => 0,
        ]);
        FestFoodOrderItem::create([
            'bill_id' => $billOnPhaseLeaf->id, 'menu_date' => '2026-09-01', 'meal_type' => 'lunch',
            'item_name' => 'Phase-only meal', 'unit_price' => 40, 'quantity' => 2, 'line_total' => 80,
        ]);
        $billOnPhaseLeaf->recalculate();
        FestFoodCoupon::create([
            'event_id' => $phaseOnlyLeaf->id, 'school_id' => $schoolA, 'coupon_code' => 'PH-1',
            'meal_type' => 'lunch', 'valid_date' => '2026-09-01', 'head_count' => 2, 'status' => 'issued',
        ]);

        $billOnRegionLeaf = FestFoodBill::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $phaseRegionLeaf->id, 'school_id' => $schoolB,
            'status' => 'open', 'payment_mode' => 'prepaid', 'amount_total' => 0, 'amount_paid' => 0,
        ]);
        FestFoodOrderItem::create([
            'bill_id' => $billOnRegionLeaf->id, 'menu_date' => '2026-09-01', 'meal_type' => 'dinner',
            'item_name' => 'Region meal', 'unit_price' => 60, 'quantity' => 3, 'line_total' => 180,
        ]);
        $billOnRegionLeaf->recalculate();
        FestFoodCoupon::create([
            'event_id' => $phaseRegionLeaf->id, 'school_id' => $schoolB, 'coupon_code' => 'RG-1',
            'meal_type' => 'dinner', 'valid_date' => '2026-09-01', 'head_count' => 3, 'status' => 'issued',
        ]);

        // Each leaf sees only its own bill, scoped purely by event_id.
        $this->assertSame([$billOnPhaseLeaf->id], FestFoodBill::where('event_id', $phaseOnlyLeaf->id)->pluck('id')->all());
        $this->assertSame([$billOnRegionLeaf->id], FestFoodBill::where('event_id', $phaseRegionLeaf->id)->pluck('id')->all());
        $this->assertSame(80.0, (float) FestFoodBill::where('event_id', $phaseOnlyLeaf->id)->value('amount_total'));
        $this->assertSame(180.0, (float) FestFoodBill::where('event_id', $phaseRegionLeaf->id)->value('amount_total'));

        // Order items are only reachable through their own bill.
        $this->assertSame(['Phase-only meal'], FestFoodOrderItem::where('bill_id', $billOnPhaseLeaf->id)->pluck('item_name')->all());
        $this->assertSame(['Region meal'], FestFoodOrderItem::where('bill_id', $billOnRegionLeaf->id)->pluck('item_name')->all());

        // Coupons don't cross leaves either.
        $this->assertSame(['PH-1'], FestFoodCoupon::where('event_id', $phaseOnlyLeaf->id)->pluck('coupon_code')->all());
        $this->assertSame(['RG-1'], FestFoodCoupon::where('event_id', $phaseRegionLeaf->id)->pluck('coupon_code')->all());
    }

    /**
     * Pins down FestPartitionService::combinedFoodSummary() against a phased hub — verified
     * directly against current source (app/Services/Events/FestPartitionService.php:531-587):
     * it filters partitions() down to partitionRole() === 'region' only. A phase x region
     * leaf gets partition_role = 'region' (same as a legacy region partition), so it IS
     * included; a non-regional phase-only leaf gets partition_role = 'phase', so its real
     * bills/coupons are silently excluded from the rollup — confirming Food Module audit
     * 2026-08-17 §2's claim precisely (not just "phase leaves excluded" in general — a
     * phase x region leaf is not excluded).
     */
    public function test_combined_food_summary_includes_phase_region_leaf_but_excludes_phase_only_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'root' => $root, 'region' => $region, 'nonRegionalPhase' => $nonRegionalPhase, 'regionalPhase' => $regionalPhase] = $this->makePhasedRoot();
        [$phaseOnlyLeaf, $phaseRegionLeaf] = $this->syncAndGetLeaves($root, $nonRegionalPhase, $regionalPhase, $region);

        FestFoodBill::create(['tenant_id' => $sahodaya->id, 'event_id' => $phaseOnlyLeaf->id, 'school_id' => 'sch-phase', 'status' => 'open', 'payment_mode' => 'prepaid', 'amount_total' => 500, 'amount_paid' => 200]);
        FestFoodCoupon::create(['event_id' => $phaseOnlyLeaf->id, 'school_id' => 'sch-phase', 'coupon_code' => 'PH-SUM-1', 'meal_type' => 'lunch', 'valid_date' => '2026-09-01', 'head_count' => 5, 'status' => 'issued']);

        FestFoodBill::create(['tenant_id' => $sahodaya->id, 'event_id' => $phaseRegionLeaf->id, 'school_id' => 'sch-region', 'status' => 'open', 'payment_mode' => 'prepaid', 'amount_total' => 300, 'amount_paid' => 100]);
        FestFoodCoupon::create(['event_id' => $phaseRegionLeaf->id, 'school_id' => 'sch-region', 'coupon_code' => 'RG-SUM-1', 'meal_type' => 'lunch', 'valid_date' => '2026-09-01', 'head_count' => 3, 'status' => 'issued']);

        $summary = app(FestPartitionService::class)->combinedFoodSummary($root);

        $this->assertSame(300.0, $summary['billing']['total'], 'Only the region-role leaf (₹300) should count — the phase-only leaf\'s ₹500 is excluded.');
        $this->assertSame(100.0, $summary['billing']['paid']);
        $this->assertSame(1, $summary['coupons']['issued']);
        $this->assertCount(1, $summary['by_region']);
    }

    /**
     * Same pin-down as above, for foodRegionDrillDownSummary() (used to render one
     * drill-down card per region on the hub's Food Menu/Coupons/Billing/Catering pages).
     * Verified directly against current source (FestPartitionService.php:341-368): same
     * partitionRole() === 'region' filter as combinedFoodSummary().
     */
    public function test_food_region_drill_down_summary_includes_phase_region_leaf_but_excludes_phase_only_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'root' => $root, 'region' => $region, 'nonRegionalPhase' => $nonRegionalPhase, 'regionalPhase' => $regionalPhase] = $this->makePhasedRoot();
        [$phaseOnlyLeaf, $phaseRegionLeaf] = $this->syncAndGetLeaves($root, $nonRegionalPhase, $regionalPhase, $region);

        $this->addMenuItem($sahodaya->id, $phaseOnlyLeaf->id, 'Phase item', 10, 'lunch');
        $this->addMenuItem($sahodaya->id, $phaseRegionLeaf->id, 'Region item', 20, 'lunch');

        FestFoodBill::create(['tenant_id' => $sahodaya->id, 'event_id' => $phaseOnlyLeaf->id, 'school_id' => 'sch-phase', 'status' => 'open', 'payment_mode' => 'prepaid', 'amount_total' => 500, 'amount_paid' => 200]);
        FestFoodBill::create(['tenant_id' => $sahodaya->id, 'event_id' => $phaseRegionLeaf->id, 'school_id' => 'sch-region', 'status' => 'open', 'payment_mode' => 'prepaid', 'amount_total' => 300, 'amount_paid' => 100]);

        $summary = app(FestPartitionService::class)->foodRegionDrillDownSummary($root);

        $this->assertCount(1, $summary, 'The phase-only leaf should not produce a drill-down card at all.');
        $this->assertSame($phaseRegionLeaf->id, $summary[0]['id']);
        $this->assertSame(1, $summary[0]['menu_items_count']);
        $this->assertSame(300.0, $summary[0]['total']);
        $this->assertSame(100.0, $summary[0]['paid']);
    }
}

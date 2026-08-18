<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestFoodBill;
use App\Models\FestFoodCoupon;
use App\Models\FestFoodMenuItem;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression tests for the food-module event/region UI-UX follow-up: FoodCoupons and
 * Catering previously had zero hub-partition awareness (a Sahodaya admin visiting either
 * on a partitioned hub saw an unexplained empty list, since coupons/catering orders are
 * always scoped to a region's own child event). FoodMenu and FoodBilling were already
 * partially hub-aware; this brings all four to the same standard, plus a shared
 * hierarchyContext() for breadcrumbs on region/phase leaf events.
 */
class FestFoodRegionAwarenessTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Region Awareness Sahodaya',
            'domain' => 'region-awareness-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RA',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return compact('sahodaya', 'admin');
    }

    public function test_hierarchy_context_on_a_standalone_event(): void
    {
        ['sahodaya' => $sahodaya] = $this->makeSahodaya();

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Standalone', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $context = $event->hierarchyContext();

        $this->assertTrue($context['is_hub']);
        $this->assertFalse($context['has_children']);
        $this->assertNull($context['parent_event']);
        $this->assertNull($context['region']);
        $this->assertNull($context['phase']);
    }

    public function test_hierarchy_context_on_a_hub_with_region_children(): void
    {
        ['sahodaya' => $sahodaya] = $this->makeSahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Hub', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'conduct_mode' => 'partitioned',
        ]);
        FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
        ]);

        $this->assertTrue($hub->hierarchyContext()['has_children']);
    }

    public function test_hierarchy_context_on_a_region_leaf_resolves_parent_and_region_name(): void
    {
        ['sahodaya' => $sahodaya] = $this->makeSahodaya();

        $region = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Malappuram', 'code' => 'MLP', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'State Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'conduct_mode' => 'partitioned',
        ]);
        $leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Malappuram Region', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'parent_event_id' => $hub->id, 'partition_key' => 'malappuram', 'partition_role' => 'region',
            'region_id' => $region->id, 'cluster_label' => 'Malappuram Zone',
        ]);

        $context = $leaf->hierarchyContext();

        $this->assertFalse($context['is_hub']);
        $this->assertSame($hub->id, $context['parent_event']['id']);
        $this->assertSame('State Kalotsav', $context['parent_event']['title']);
        $this->assertSame('Malappuram', $context['region']['name']);
        $this->assertSame('Malappuram Zone', $context['cluster_label']);
    }

    public function test_hierarchy_context_resolves_phase_name(): void
    {
        ['sahodaya' => $sahodaya] = $this->makeSahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Hub', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);
        $phase = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'Off Stage', 'code' => 'OS', 'sort_order' => 1]);
        $leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Off Stage Leaf', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase->id,
        ]);

        $this->assertSame('Off Stage', $leaf->hierarchyContext()['phase']['name']);
    }

    public function test_food_region_drill_down_summary_reports_per_region_stats(): void
    {
        ['sahodaya' => $sahodaya] = $this->makeSahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Hub', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'conduct_mode' => 'partitioned',
        ]);
        $region = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
            'cluster_label' => 'Region A Zone',
        ]);

        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $region->id, 'menu_date' => '2026-09-01', 'meal_type' => 'lunch', 'name' => 'Meals', 'price' => 50, 'is_available' => true, 'sort_order' => 0]);
        FestFoodBill::create(['tenant_id' => $sahodaya->id, 'event_id' => $region->id, 'school_id' => 'sch-1', 'status' => 'open', 'payment_mode' => 'prepaid', 'amount_total' => 100, 'amount_paid' => 40]);
        FestFoodCoupon::create(['event_id' => $region->id, 'school_id' => 'sch-1', 'coupon_code' => 'RC-1', 'meal_type' => 'lunch', 'valid_date' => '2026-09-01', 'head_count' => 5, 'status' => 'issued']);
        FestCateringOrder::create(['event_id' => $region->id, 'school_id' => 'sch-1', 'meal_date' => '2026-09-01', 'meal_type' => 'lunch', 'head_count' => 30, 'status' => 'confirmed']);

        $summary = app(\App\Services\Events\FestPartitionService::class)->foodRegionDrillDownSummary($hub);

        $this->assertCount(1, $summary);
        $row = $summary[0];
        $this->assertSame($region->id, $row['id']);
        $this->assertSame('Region A Zone', $row['label']);
        $this->assertSame(1, $row['menu_items_count']);
        $this->assertSame(1, $row['bills_count']);
        $this->assertSame(100.0, $row['total']);
        $this->assertSame(40.0, $row['paid']);
        $this->assertSame(1, $row['coupons_issued']);
        $this->assertSame(30, $row['catering_head_count']);
    }

    public function test_food_coupons_page_is_hub_aware(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin] = $this->makeSahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Hub', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'conduct_mode' => 'partitioned',
        ]);
        FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'cluster_label' => 'Region A Zone',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$hub->id}/food-coupons")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/FoodCoupons', false)
                ->where('isPartitionedHub', true)
                ->where('foodRegionSummary.0.label', 'Region A Zone'));
    }

    public function test_catering_page_is_hub_aware(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin] = $this->makeSahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Hub', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'conduct_mode' => 'partitioned',
        ]);
        FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'cluster_label' => 'Region A Zone',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$hub->id}/catering")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Catering', false)
                ->where('isPartitionedHub', true)
                ->where('foodRegionSummary.0.label', 'Region A Zone'));
    }

    public function test_food_menu_page_carries_hierarchy_for_a_region_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin] = $this->makeSahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Hub Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'conduct_mode' => 'partitioned',
        ]);
        $region = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
            'cluster_label' => 'Region A Zone',
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$region->id}/food-menu")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/FoodMenu', false)
                ->where('hierarchy.is_hub', false)
                ->where('hierarchy.parent_event.title', 'Hub Event')
                ->where('hierarchy.cluster_label', 'Region A Zone'));
    }
}

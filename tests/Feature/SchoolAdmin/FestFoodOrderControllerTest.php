<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestFoodBill;
use App\Models\FestFoodMenuItem;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestSchoolPartitionService;
use App\Services\Events\FestSchoolPhaseRegionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for App\Http\Controllers\SchoolAdmin\FestFoodOrderController — the school
 * self-service food ordering screen. Previously had zero test coverage at all (grep for
 * "food_cutoff_at" across tests/ returned nothing before this file). Exercises:
 * - Correct event_id targeting + partition awareness (normal / legacy region leaf / phase
 *   leaf), reusing FestRegistrationRouterService::assertSchoolCanAccess() the same way
 *   registration does.
 * - The food_cutoff_at gate in assertAccess() (app/Http/Controllers/SchoolAdmin/
 *   FestFoodOrderController.php:25-31), including that it's a no-op for any event that
 *   isn't phase_mode_enabled with a source_phase_id.
 * - is_available and max_per_school enforcement in addItem().
 */
class FestFoodOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, schoolAdmin: User} */
    private function makeSahodayaAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Food Order Sahodaya',
            'domain' => 'food-order-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'FO',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Food Order School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'schoolAdmin');
    }

    private function makeStandaloneEvent(Tenant $sahodaya, array $overrides = []): FestEvent
    {
        return FestEvent::create(array_merge([
            'tenant_id' => $sahodaya->id,
            'title' => 'Standalone Fest '.Str::random(4),
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ], $overrides));
    }

    private function addMenuItem(string $tenantId, int $eventId, array $overrides = []): FestFoodMenuItem
    {
        return FestFoodMenuItem::create(array_merge([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'menu_date' => '2026-09-01',
            'meal_type' => 'lunch',
            'name' => 'Meals',
            'price' => 50,
            'is_available' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * A phased-workflow root with a single non-regional phase, already synced. Overrides
     * are applied to the phase (e.g. food_cutoff_at) BEFORE the sync runs, so
     * FestPhaseTopologyService::syncChildPhase() clones them onto the leaf's own phase row.
     *
     * @return array{0: FestEvent, 1: FestEventPhase}
     */
    private function makeNonRegionalPhaseRoot(Tenant $sahodaya, array $phaseOverrides = []): array
    {
        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Phased Root',
            'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
        ]);

        $phase = app(FestEventPhaseService::class)->createPhase($root, [
            'name' => 'Digi Fest', 'code' => 'DIGI', 'is_regional' => false,
        ]);
        if ($phaseOverrides !== []) {
            $phase->update($phaseOverrides);
        }

        app(FestPhaseTopologyService::class)->sync($root->fresh());

        $leaf = FestEvent::where('parent_event_id', $root->id)->where('source_phase_id', $phase->id)->firstOrFail();

        return [$leaf, $phase->fresh()];
    }

    public function test_school_can_order_food_on_a_normal_standalone_event(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        $event = $this->makeStandaloneEvent($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $event->id);

        $response = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 2]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $bill = FestFoodBill::where('event_id', $event->id)->where('school_id', $school->id)->first();
        $this->assertNotNull($bill);
        $this->assertSame(100.0, (float) $bill->amount_total);
    }

    public function test_school_can_order_food_on_its_assigned_legacy_region_leaf_but_not_a_sibling_region_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region Hub', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'conduct_mode' => 'partitioned',
        ]);
        $regionA = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
        ]);
        $regionB = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region B', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-b', 'partition_role' => 'region',
        ]);
        $menuItemA = $this->addMenuItem($sahodaya->id, $regionA->id, ['name' => 'Region A meal']);
        $menuItemB = $this->addMenuItem($sahodaya->id, $regionB->id, ['name' => 'Region B meal']);

        app(FestSchoolPartitionService::class)->assign($hub, $school->id, 'region-a');

        $ok = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $regionA->id,
        ]), ['menu_item_id' => $menuItemA->id, 'quantity' => 1]);
        $ok->assertRedirect();
        $this->assertSame(1, FestFoodBill::where('event_id', $regionA->id)->where('school_id', $school->id)->count());

        // Same school, sibling region it was never assigned to.
        $blocked = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $regionB->id,
        ]), ['menu_item_id' => $menuItemB->id, 'quantity' => 1]);
        $blocked->assertStatus(403);
        $this->assertSame(0, FestFoodBill::where('event_id', $regionB->id)->count());
    }

    public function test_school_can_order_food_on_its_selected_phase_region_leaf_but_not_a_sibling_region_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phased Root', 'event_type' => 'kalotsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'workflow_mode' => FestPhasedWorkflowService::MODE, 'phase_mode_enabled' => true,
        ]);
        $regionX = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region X', 'code' => 'RGX', 'is_active' => true]);
        $regionY = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region Y', 'code' => 'RGY', 'is_active' => true]);

        $phase = app(FestEventPhaseService::class)->createPhase($root, [
            'name' => 'Off Stage', 'code' => 'OFF_STAGE', 'is_regional' => true,
        ]);
        app(FestPhasedWorkflowService::class)->syncAllowedRegions($phase, [$regionX->id, $regionY->id]);

        app(FestPhaseTopologyService::class)->sync($root->fresh());

        $leafX = FestEvent::where('parent_event_id', $root->id)->where('source_phase_id', $phase->id)->where('region_id', $regionX->id)->firstOrFail();
        $leafY = FestEvent::where('parent_event_id', $root->id)->where('source_phase_id', $phase->id)->where('region_id', $regionY->id)->firstOrFail();

        $menuItemX = $this->addMenuItem($sahodaya->id, $leafX->id, ['name' => 'Region X meal']);
        $menuItemY = $this->addMenuItem($sahodaya->id, $leafY->id, ['name' => 'Region Y meal']);

        app(FestSchoolPhaseRegionService::class)->select($root, $phase, $school->id, $regionX->id);

        $ok = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $leafX->id,
        ]), ['menu_item_id' => $menuItemX->id, 'quantity' => 1]);
        $ok->assertRedirect();
        $this->assertSame(1, FestFoodBill::where('event_id', $leafX->id)->where('school_id', $school->id)->count());

        $blocked = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $leafY->id,
        ]), ['menu_item_id' => $menuItemY->id, 'quantity' => 1]);
        $blocked->assertStatus(403);
        $this->assertSame(0, FestFoodBill::where('event_id', $leafY->id)->count());
    }

    public function test_school_can_order_food_on_a_non_regional_phase_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        [$leaf] = $this->makeNonRegionalPhaseRoot($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $leaf->id);

        $response = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $leaf->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 1]);

        $response->assertRedirect();
        $this->assertSame(1, FestFoodBill::where('event_id', $leaf->id)->where('school_id', $school->id)->count());
    }

    public function test_food_cutoff_in_the_past_blocks_ordering_on_a_phase_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        [$leaf] = $this->makeNonRegionalPhaseRoot($sahodaya, ['food_cutoff_at' => now()->subDay()]);
        $menuItem = $this->addMenuItem($sahodaya->id, $leaf->id);

        $response = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $leaf->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 1]);

        // FestFoodOrderController::assertAccess() — abort_if($cutoff && now()->gt($cutoff),
        // 422, 'Food ordering has closed for this competition phase.').
        $response->assertStatus(422);
        $this->assertSame(0, FestFoodBill::where('event_id', $leaf->id)->count());
    }

    public function test_food_cutoff_in_the_future_allows_ordering_on_a_phase_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        [$leaf] = $this->makeNonRegionalPhaseRoot($sahodaya, ['food_cutoff_at' => now()->addDay()]);
        $menuItem = $this->addMenuItem($sahodaya->id, $leaf->id);

        $response = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $leaf->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 1]);

        $response->assertRedirect();
        $this->assertSame(1, FestFoodBill::where('event_id', $leaf->id)->where('school_id', $school->id)->count());
    }

    public function test_null_food_cutoff_allows_ordering_on_a_phase_leaf(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        [$leaf] = $this->makeNonRegionalPhaseRoot($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $leaf->id);

        $response = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $leaf->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 1]);

        $response->assertRedirect();
        $this->assertSame(1, FestFoodBill::where('event_id', $leaf->id)->where('school_id', $school->id)->count());
    }

    /**
     * Documented gap (Food Module audit 2026-08-17, Finding 13): the food_cutoff_at check
     * only runs when phase_mode_enabled && source_phase_id are both set — a plain standard
     * event has neither, so it has no ordering cutoff at all, no matter how late "now" is.
     */
    public function test_food_cutoff_is_never_enforced_on_a_non_phase_event(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        $event = $this->makeStandaloneEvent($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $event->id);

        Carbon::setTestNow(now()->addYears(3));
        try {
            $response = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
                'tenantId' => $school->id, 'event' => $event->id,
            ]), ['menu_item_id' => $menuItem->id, 'quantity' => 1]);
        } finally {
            Carbon::setTestNow();
        }

        $response->assertRedirect();
        $this->assertSame(1, FestFoodBill::where('event_id', $event->id)->count());
    }

    public function test_ordering_an_unavailable_menu_item_is_rejected(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        $event = $this->makeStandaloneEvent($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $event->id, ['is_available' => false]);

        // addItem() queries ->where('is_available', true)->findOrFail(...) — an unavailable
        // item simply isn't found, surfacing as a 404, not a validation error.
        $response = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 1]);

        $response->assertStatus(404);
        $this->assertSame(0, FestFoodBill::where('event_id', $event->id)->count());
    }

    public function test_ordering_beyond_max_per_school_across_multiple_orders_is_rejected(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        $event = $this->makeStandaloneEvent($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $event->id, ['name' => 'Limited Dessert', 'max_per_school' => 5]);

        $first = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 5]);
        $first->assertRedirect();

        $second = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 1]);
        $second->assertStatus(422);

        $bill = FestFoodBill::where('event_id', $event->id)->where('school_id', $school->id)->firstOrFail();
        $this->assertSame(5, (int) $bill->orderItems()->sum('quantity'));
    }

    public function test_quantity_validation_rejects_out_of_range_values(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        $event = $this->makeStandaloneEvent($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $event->id);

        $zero = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 0]);
        $zero->assertSessionHasErrors('quantity');

        $tooMany = $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 10000]);
        $tooMany->assertSessionHasErrors('quantity');

        $this->assertSame(0, FestFoodBill::where('event_id', $event->id)->count());
    }

    public function test_removing_an_order_item_recalculates_the_bill_total(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        $event = $this->makeStandaloneEvent($sahodaya);
        $menuItem = $this->addMenuItem($sahodaya->id, $event->id, ['price' => 40]);

        $this->actingAs($schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $school->id, 'event' => $event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 2]);

        $bill = FestFoodBill::where('event_id', $event->id)->where('school_id', $school->id)->firstOrFail();
        $this->assertSame(80.0, (float) $bill->amount_total);
        $orderItem = $bill->orderItems()->firstOrFail();

        $response = $this->actingAs($schoolAdmin)->delete(route('school.food-order.items.destroy', [
            'tenantId' => $school->id, 'event' => $event->id, 'orderItem' => $orderItem->id,
        ]));

        $response->assertRedirect();
        $this->assertSame(0.0, (float) $bill->fresh()->amount_total);
        $this->assertSame(0, $bill->orderItems()->count());
    }
}

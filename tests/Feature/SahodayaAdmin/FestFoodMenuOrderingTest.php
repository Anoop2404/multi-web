<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodMenuItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestFoodMenuSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression tests for the food-menu ordering/date-scoping follow-up to the Food Module
 * audit (2026-08-17): meal_type is a plain varchar column, so a raw orderBy('meal_type')
 * sorts alphabetically ('dinner' before 'lunch') instead of chronologically, and menu_date
 * previously had no relationship to the event's own event_start/event_end at all.
 */
class FestFoodMenuOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndEvent(?string $start = null, ?string $end = null): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Menu Ordering Sahodaya',
            'domain' => 'menu-ordering-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'MO',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Menu Ordering Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'event_start' => $start,
            'event_end' => $end,
        ]);

        return compact('sahodaya', 'admin', 'event');
    }

    public function test_sort_for_display_orders_meals_chronologically_not_alphabetically(): void
    {
        ['sahodaya' => $sahodaya, 'event' => $event] = $this->makeSahodayaAndEvent();

        // Created out of order, and 'dinner' < 'lunch' alphabetically — a raw string sort
        // would put dinner before lunch, which is wrong for a same-day menu listing.
        foreach (['dinner', 'breakfast', 'other', 'lunch', 'tea', 'snacks'] as $meal) {
            FestFoodMenuItem::create([
                'tenant_id' => $sahodaya->id,
                'event_id' => $event->id,
                'menu_date' => '2026-09-01',
                'meal_type' => $meal,
                'name' => ucfirst($meal).' item',
                'price' => 10,
                'is_available' => true,
                'sort_order' => 0,
            ]);
        }

        $sorted = FestFoodMenuItem::sortForDisplay(FestFoodMenuItem::forEvent($event->id)->get());

        $this->assertSame(
            ['breakfast', 'lunch', 'snacks', 'tea', 'dinner', 'other'],
            $sorted->pluck('meal_type')->all()
        );
    }

    public function test_sort_for_display_orders_by_date_before_meal_type(): void
    {
        ['sahodaya' => $sahodaya, 'event' => $event] = $this->makeSahodayaAndEvent();

        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'menu_date' => '2026-09-02', 'meal_type' => 'breakfast', 'name' => 'Day 2 breakfast', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);
        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'menu_date' => '2026-09-01', 'meal_type' => 'dinner', 'name' => 'Day 1 dinner', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);

        $sorted = FestFoodMenuItem::sortForDisplay(FestFoodMenuItem::forEvent($event->id)->get());

        $this->assertSame(['Day 1 dinner', 'Day 2 breakfast'], $sorted->pluck('name')->all());
    }

    public function test_menu_item_creation_is_rejected_outside_the_event_date_range(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-03');

        $response = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu", [
            'menu_date' => '2026-09-10',
            'meal_type' => 'lunch',
            'name' => 'Out of range meal',
            'price' => 50,
        ]);

        $response->assertSessionHasErrors('menu_date');
        $this->assertSame(0, FestFoodMenuItem::where('event_id', $event->id)->count());
    }

    public function test_menu_item_creation_succeeds_within_the_event_date_range(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-03');

        $response = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu", [
            'menu_date' => '2026-09-02',
            'meal_type' => 'lunch',
            'name' => 'In range meal',
            'price' => 50,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, FestFoodMenuItem::where('event_id', $event->id)->count());
    }

    public function test_menu_item_creation_is_unrestricted_when_event_has_no_dates_set(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent(null, null);

        $response = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu", [
            'menu_date' => '2026-12-25',
            'meal_type' => 'lunch',
            'name' => 'Any date meal',
            'price' => 50,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, FestFoodMenuItem::where('event_id', $event->id)->count());
    }

    public function test_index_page_receives_event_dates_and_chronologically_sorted_items(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'menu_date' => '2026-09-01', 'meal_type' => 'dinner', 'name' => 'Dinner', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);
        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'menu_date' => '2026-09-01', 'meal_type' => 'breakfast', 'name' => 'Breakfast', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/FoodMenu', false)
                ->where('eventDates', ['2026-09-01', '2026-09-02'])
                ->where('menuItems.0.name', 'Breakfast')
                ->where('menuItems.1.name', 'Dinner'));
    }

    /**
     * Regression test: `menu_date` was cast as a plain `date` (no format), so Eloquent's
     * default JSON serialization converted the app-timezone (Asia/Kolkata, UTC+5:30)
     * midnight instant to UTC before appending "Z" — turning "2026-09-01" into
     * "2026-08-31T18:30:00.000000Z" on the wire. The frontend's date-prefix parser then
     * displayed the wrong (previous) day everywhere: the menu page, order items, and
     * printed food coupons. Casting as `date:Y-m-d` makes Eloquent serialize the plain
     * calendar date with no timezone conversion at all.
     */
    public function test_menu_date_is_not_shifted_a_day_earlier_in_the_inertia_response(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'menu_date' => '2026-09-01', 'meal_type' => 'lunch', 'name' => 'Meals', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/FoodMenu', false)
                ->where('menuItems.0.menu_date', '2026-09-01'));
    }

    public function test_region_sync_skips_items_outside_the_regions_own_date_range(): void
    {
        ['sahodaya' => $sahodaya, 'event' => $hub] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-05');

        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $hub->id, 'menu_date' => '2026-09-01', 'meal_type' => 'lunch', 'name' => 'Hub-only-day item', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);
        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $hub->id, 'menu_date' => '2026-09-03', 'meal_type' => 'lunch', 'name' => 'Shared-day item', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);

        // A region that only runs 09-03..09-05 — narrower than the hub's 09-01..09-05.
        $region = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region A',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'parent_event_id' => $hub->id,
            'event_start' => '2026-09-03',
            'event_end' => '2026-09-05',
        ]);

        $created = app(FestFoodMenuSyncService::class)->copyMenuToPartition($hub, $region);

        $this->assertSame(1, $created);
        $this->assertSame(
            ['Shared-day item'],
            FestFoodMenuItem::where('event_id', $region->id)->pluck('name')->all()
        );
    }

    public function test_region_sync_copies_everything_when_region_has_no_dates_set_yet(): void
    {
        ['sahodaya' => $sahodaya, 'event' => $hub] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-05');

        FestFoodMenuItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $hub->id, 'menu_date' => '2026-09-01', 'meal_type' => 'lunch', 'name' => 'Any item', 'price' => 10, 'is_available' => true, 'sort_order' => 0]);

        $region = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region B (dates not set yet)',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'parent_event_id' => $hub->id,
        ]);

        $created = app(FestFoodMenuSyncService::class)->copyMenuToPartition($hub, $region);

        $this->assertSame(1, $created);
    }
}

<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodCatalogItem;
use App\Models\FestFoodMenuItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression tests for the food-item catalog workflow: define an item once (name,
 * description, default price), then bulk-assign it onto date+meal-type slots instead of
 * re-entering the same item on every date it's served. Mirrors the item/phase assignment
 * pattern already used on the Phases page, adapted for a create-many-slots action instead
 * of an overwrite-one-FK action, since a food item is typically served on many dates.
 */
class FestFoodCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndEvent(?string $start = null, ?string $end = null): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Catalog Sahodaya',
            'domain' => 'food-catalog-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'FC', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Catalog Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing', 'event_start' => $start, 'event_end' => $end,
        ]);

        return compact('sahodaya', 'admin', 'event');
    }

    public function test_catalog_item_can_be_created_updated_and_deleted(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent();

        $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-catalog", [
            'name' => 'Idli', 'description' => 'Steamed rice cake', 'default_price' => 10,
        ])->assertSessionDoesntHaveErrors();

        $item = FestFoodCatalogItem::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('Idli', $item->name);
        $this->assertEquals(10.00, $item->default_price);
        $this->assertTrue($item->is_active);

        $this->actingAs($admin)->put("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-catalog/{$item->id}", [
            'name' => 'Idli (2 pcs)', 'description' => 'Steamed rice cake', 'default_price' => 12, 'is_active' => false,
        ])->assertSessionDoesntHaveErrors();

        $item->refresh();
        $this->assertSame('Idli (2 pcs)', $item->name);
        $this->assertEquals(12.00, $item->default_price);
        $this->assertFalse($item->is_active);

        $this->actingAs($admin)->delete("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-catalog/{$item->id}")
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(0, FestFoodCatalogItem::where('event_id', $event->id)->count());
    }

    public function test_deleting_a_catalog_item_does_not_delete_menu_items_already_scheduled_from_it(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        $catalogItem = FestFoodCatalogItem::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Idli', 'default_price' => 10,
        ]);

        $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu/assign-catalog-items", [
            'catalog_item_ids' => [$catalogItem->id], 'menu_date' => '2026-09-01', 'meal_type' => 'breakfast',
        ])->assertSessionDoesntHaveErrors();

        $menuItem = FestFoodMenuItem::where('event_id', $event->id)->firstOrFail();
        $this->assertSame($catalogItem->id, $menuItem->catalog_item_id);

        $this->actingAs($admin)->delete("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-catalog/{$catalogItem->id}")
            ->assertSessionDoesntHaveErrors();

        $menuItem->refresh();
        $this->assertNull($menuItem->catalog_item_id);
        $this->assertSame('Idli', $menuItem->name); // the scheduled slot keeps its own snapshot
    }

    public function test_assign_creates_one_menu_item_per_selected_catalog_item_on_the_chosen_slot(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        $idli = FestFoodCatalogItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Idli', 'default_price' => 10]);
        $dosa = FestFoodCatalogItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Dosa', 'default_price' => 15]);

        $response = $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu/assign-catalog-items", [
            'catalog_item_ids' => [$idli->id, $dosa->id], 'menu_date' => '2026-09-01', 'meal_type' => 'breakfast',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(2, FestFoodMenuItem::where('event_id', $event->id)->count());

        $created = FestFoodMenuItem::where('event_id', $event->id)->orderBy('name')->get();
        $this->assertSame(['Dosa', 'Idli'], $created->pluck('name')->all());
        $this->assertTrue($created->every(fn ($m) => $m->menu_date->format('Y-m-d') === '2026-09-01' && $m->meal_type === 'breakfast'));
    }

    public function test_assigning_an_item_already_on_that_slot_is_skipped_not_duplicated(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        $idli = FestFoodCatalogItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Idli', 'default_price' => 10]);

        $payload = ['catalog_item_ids' => [$idli->id], 'menu_date' => '2026-09-01', 'meal_type' => 'breakfast'];
        $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu/assign-catalog-items", $payload);
        $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu/assign-catalog-items", $payload);

        $this->assertSame(1, FestFoodMenuItem::where('event_id', $event->id)->count());
    }

    public function test_assign_respects_the_event_date_range(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        $idli = FestFoodCatalogItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Idli', 'default_price' => 10]);

        $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu/assign-catalog-items", [
            'catalog_item_ids' => [$idli->id], 'menu_date' => '2026-09-10', 'meal_type' => 'breakfast',
        ])->assertSessionHasErrors('menu_date');

        $this->assertSame(0, FestFoodMenuItem::where('event_id', $event->id)->count());
    }

    public function test_assign_rejects_a_catalog_item_from_another_event(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        $otherEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Other Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);
        $foreignItem = FestFoodCatalogItem::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $otherEvent->id, 'name' => 'Foreign Idli', 'default_price' => 10,
        ]);

        $this->actingAs($admin)->post("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu/assign-catalog-items", [
            'catalog_item_ids' => [$foreignItem->id], 'menu_date' => '2026-09-01', 'meal_type' => 'breakfast',
        ])->assertSessionHasErrors('catalog_item_ids.0');

        $this->assertSame(0, FestFoodMenuItem::where('event_id', $event->id)->count());
    }

    public function test_food_menu_index_returns_catalog_items_with_slot_counts(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event] = $this->makeSahodayaAndEvent('2026-09-01', '2026-09-02');

        $idli = FestFoodCatalogItem::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Idli', 'default_price' => 10]);
        FestFoodMenuItem::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'catalog_item_id' => $idli->id,
            'menu_date' => '2026-09-01', 'meal_type' => 'breakfast', 'name' => 'Idli', 'price' => 10,
            'is_available' => true, 'sort_order' => 0,
        ]);
        FestFoodMenuItem::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'catalog_item_id' => $idli->id,
            'menu_date' => '2026-09-02', 'meal_type' => 'breakfast', 'name' => 'Idli', 'price' => 10,
            'is_available' => true, 'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-menu")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/FoodMenu', false)
                ->where('catalogItems.0.name', 'Idli')
                ->where('catalogItems.0.slots_count', 2));
    }
}

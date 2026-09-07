<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bulk item_code editor — set once for many items instead of opening each item's own edit
 * form (see Items/Details.vue). Category/gender/participant_type are read-only reference
 * columns on that page; they stay editable only from the item's own form.
 */
class FestBulkItemDetailsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent, items: array<FestEventItem>} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Item Details Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BID', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Item Details Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $items = [
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Recitation Malayalam', 'category' => 'literary', 'gender' => 'mixed', 'participant_type' => 'individual', 'is_enabled' => true]),
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Song', 'category' => 'music', 'gender' => 'female', 'participant_type' => 'group', 'is_enabled' => true]),
        ];

        return compact('sahodaya', 'admin', 'event', 'items');
    }

    public function test_bulk_update_sets_item_code_for_every_listed_item(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'items' => $items] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.items.bulk-details', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'items' => [
                ['id' => $items[0]->id, 'item_code' => '101'],
                ['id' => $items[1]->id, 'item_code' => '501'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertSessionHas('success');

        $this->assertSame('101', $items[0]->fresh()->item_code);
        $this->assertSame('501', $items[1]->fresh()->item_code);

        // Category/gender/participant_type are read-only on this page — untouched by the save.
        $this->assertSame('literary', $items[0]->fresh()->category);
        $this->assertSame('mixed', $items[0]->fresh()->gender);
        $this->assertSame('individual', $items[0]->fresh()->participant_type);
    }

    public function test_bulk_update_can_clear_a_code_back_to_blank(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'items' => $items] = $this->fixture();
        $items[0]->update(['item_code' => '101']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.items.bulk-details', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'items' => [
                ['id' => $items[0]->id, 'item_code' => null],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNull($items[0]->fresh()->item_code);
    }

    public function test_bulk_update_only_affects_items_from_this_event(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'items' => $items] = $this->fixture();

        $otherEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Other Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $foreignItem = FestEventItem::create(['event_id' => $otherEvent->id, 'title' => 'Foreign Item', 'participant_type' => 'individual']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.items.bulk-details', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'items' => [
                ['id' => $items[0]->id, 'item_code' => '101'],
                ['id' => $foreignItem->id, 'item_code' => '999'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('101', $items[0]->fresh()->item_code);
        $this->assertNull($foreignItem->fresh()->item_code, 'An item belonging to a different event must not be updated.');
    }

    public function test_details_page_lists_every_item_with_its_current_code_and_reference_fields(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'items' => $items] = $this->fixture();
        $items[0]->update(['item_code' => '101']);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.items.details', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('taxonomy', $props);

        $rows = collect($props['groupedItems'] ?? [])->flatten(1);
        $rowA = $rows->firstWhere('id', $items[0]->id);
        $this->assertSame('101', $rowA['item_code']);
        $this->assertSame('literary', $rowA['category']);
        $this->assertSame('mixed', $rowA['gender']);
        $this->assertSame('individual', $rowA['participant_type']);
    }
}

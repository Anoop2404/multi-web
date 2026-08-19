<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two real bugs found while investigating a production data-quality issue (custom items
 * with no item_code, some looking like accidental duplicates): (1) the Edit form has a
 * visible Item Code field, but updateItem()'s validation never listed it, so anything
 * typed there was silently discarded; (2) destroyItem() had no guard against active
 * registrations, and the DB column is nullOnDelete, so a hard delete would have silently
 * orphaned real registration data. Fixed alongside making the delete a soft delete
 * (FestEventItem::SoftDeletes) with a restore path, so a mistaken delete is recoverable.
 */
class FestEventItemCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Item CRUD Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ICT', 'student_data_mode' => 'counts_only']);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return [$sahodaya, $admin];
    }

    public function test_updating_an_item_persists_a_newly_added_item_code(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'draft']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Dance', 'owner_level' => 'sahodaya', 'is_enabled' => true]);
        $this->assertNull($item->item_code);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.items.update', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'item' => $item->id,
        ]), ['title' => 'Group Dance', 'item_code' => 'GD-01']);

        $response->assertStatus(302);
        $this->assertSame('GD-01', $item->fresh()->item_code);
    }

    public function test_delete_is_blocked_when_the_item_has_registrations(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'draft']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Dance', 'owner_level' => 'sahodaya', 'is_enabled' => true]);
        FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => (string) Str::uuid(), 'status' => 'approved', 'submitted_at' => now()]);

        $response = $this->actingAs($admin)->delete(route('sahodaya.events.items.destroy', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'item' => $item->id,
        ]));

        $response->assertStatus(422);
        $this->assertNotNull(FestEventItem::find($item->id), 'item must not be deleted while registrations exist');
    }

    public function test_delete_soft_deletes_and_can_be_restored_when_the_item_has_no_registrations(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'draft']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Dance', 'owner_level' => 'sahodaya', 'is_enabled' => true]);

        $delete = $this->actingAs($admin)->delete(route('sahodaya.events.items.destroy', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'item' => $item->id,
        ]));
        $delete->assertStatus(302);
        $this->assertNull(FestEventItem::find($item->id), 'excluded from default queries once soft-deleted');
        $this->assertNotNull(FestEventItem::withTrashed()->find($item->id), 'row itself must still exist');

        $restore = $this->actingAs($admin)->post(route('sahodaya.events.items.restore', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'item' => $item->id,
        ]));
        $restore->assertStatus(302);
        $this->assertNotNull(FestEventItem::find($item->id), 'visible again after restore');
        $this->assertNull(FestEventItem::withTrashed()->find($item->id)->deleted_at);
    }

    public function test_state_catalog_items_cannot_be_deleted(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'draft']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Recitation', 'owner_level' => 'state', 'is_enabled' => true]);

        $response = $this->actingAs($admin)->delete(route('sahodaya.events.items.destroy', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'item' => $item->id,
        ]));

        $response->assertStatus(422);
        $this->assertNotNull(FestEventItem::find($item->id));
    }
}

<?php

namespace Tests\Feature\SahodayaAdmin;

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
 * Plain item catalog listing (Name, Code, Gender, Category, Group/Individual) for the
 * Bulk Sheets picker's "Items List" report -- no participant/mark data, just the items
 * themselves, using the same parseBulkSheetFilters() selection as the other bulk reports.
 */
class FestItemsListReportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: list<FestEventItem>} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Items List Sahodaya',
            'domain' => 'items-list-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'IL', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Items List Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $items = [
            FestEventItem::create([
                'event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => '101',
                'participant_type' => 'individual', 'gender' => 'male', 'class_group' => 'hs', 'is_enabled' => true,
            ]),
            FestEventItem::create([
                'event_id' => $event->id, 'title' => 'Group Dance', 'item_code' => '205',
                'participant_type' => 'team', 'gender' => 'female', 'class_group' => 'lp', 'is_enabled' => true,
            ]),
        ];

        return [$sahodaya, $event, $admin, $items];
    }

    public function test_item_ids_downloads_a_pdf_for_exactly_the_selected_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/items-list?item_ids={$items[0]->id},{$items[1]->id}"
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('2-items', urldecode($response->headers->get('content-disposition')));
    }

    public function test_preview_streams_inline_instead_of_downloading(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/items-list?item_ids={$items[0]->id}&preview=1"
        );

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    /**
     * No item_ids/item_id/phase_id/area_id at all -- the "whole event" bulk download.
     * Matches the same "no filter = every enabled item" convention markEntrySheet()/
     * resultDeclarationSheet() already had before bulk selection existed -- the filename
     * doesn't get a "-N-items" suffix in this case (that's reserved for a bulk filter
     * that actually narrowed something), it's just this simply succeeding that proves
     * every enabled item was found rather than 404ing on an empty query.
     */
    public function test_no_filters_at_all_covers_every_enabled_item_in_the_event(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/items-list"
        );

        $response->assertOk();
    }

    public function test_no_items_selected_and_none_in_event_returns_404(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Empty Items List Sahodaya',
            'domain' => 'empty-items-list-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'EIL', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Empty Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/items-list"
        );

        $response->assertNotFound();
    }
}

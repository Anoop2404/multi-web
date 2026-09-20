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
 * The Digital Sum Sheet (cumulativeSheet()) was the one sheet download still stuck at
 * "exactly one item" (?item_id=), unlike its Judge Sheets and Result Declaration Sheet
 * siblings which already got checkbox multi-select (?item_ids=) and phase/area filters --
 * this brings it in line with the same parseBulkSheetFilters() pattern.
 */
class FestMarkCriteriaSheetBulkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: list<FestEventItem>} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Sum Sheet Sahodaya',
            'domain' => 'bulk-sum-sheet-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BSS', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Sum Sheet Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $items = [
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item One', 'participant_type' => 'individual', 'is_enabled' => true]),
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item Two', 'participant_type' => 'individual', 'is_enabled' => true]),
        ];

        return [$sahodaya, $event, $admin, $items];
    }

    public function test_item_ids_downloads_one_combined_pdf_for_the_selected_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        [$one, $two] = $items;

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-criteria-sheet?item_ids={$one->id},{$two->id}");

        $response->assertOk();
        $this->assertStringContainsString('2-items', urldecode($response->headers->get('content-disposition')));
    }

    public function test_existing_single_item_behavior_is_unchanged(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-criteria-sheet?item_id={$items[0]->id}");

        $response->assertOk();
        $this->assertStringContainsString('item-one', urldecode($response->headers->get('content-disposition')));
    }

    public function test_no_item_selected_at_all_is_rejected(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-criteria-sheet");

        $response->assertStatus(422);
    }
}

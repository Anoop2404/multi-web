<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestCompetitionArea;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mark Entry Sheet (and its Result Declaration Sheet sibling) could only ever be
 * downloaded one item at a time (?item_id=) or for every enabled item in the whole
 * event (no param) -- nothing in between. This adds a checkbox multi-select (?item_ids=
 * a,b,c) plus phase/competition-area filters, reusing the exact same per-item loop that
 * already builds one combined PDF -- just a different item filter, not new PDF-building
 * logic.
 */
class FestMarkEntrySheetBulkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: list<FestEventItem>} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Sheet Sahodaya',
            'domain' => 'bulk-sheet-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BS', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Sheet Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Phase 1', 'code' => 'P1', 'sort_order' => 1]);
        $phase2 = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Phase 2', 'code' => 'P2', 'sort_order' => 2]);
        $areaA = FestCompetitionArea::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Area A']);
        $areaB = FestCompetitionArea::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Area B']);

        $items = [
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item One', 'participant_type' => 'individual', 'is_enabled' => true, 'phase_id' => $phase1->id, 'area_id' => $areaA->id]),
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item Two', 'participant_type' => 'individual', 'is_enabled' => true, 'phase_id' => $phase1->id, 'area_id' => $areaB->id]),
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item Three', 'participant_type' => 'individual', 'is_enabled' => true, 'phase_id' => $phase2->id, 'area_id' => $areaA->id]),
        ];

        return [$sahodaya, $event, $admin, $items];
    }

    public function test_item_ids_downloads_a_combined_sheet_for_exactly_the_selected_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        [$one, , $three] = $items;

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-entry-sheet?item_ids={$one->id},{$three->id}");

        $response->assertOk();
        $response->assertHeader('content-disposition');
        // Str::slug() turns "2 items" into "2-items" in the generated filename.
        $this->assertStringContainsString('2-items', urldecode($response->headers->get('content-disposition')));
    }

    public function test_phase_id_filters_to_only_that_phases_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        $phase1Id = $items[0]->phase_id;

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-entry-sheet?phase_id={$phase1Id}");

        $response->assertOk();
        // Phase 1 has Item One + Item Two -- "2-items" in the generated filename proves
        // the phase filter actually narrowed the query, not just that the request 200s.
        $this->assertStringContainsString('2-items', urldecode($response->headers->get('content-disposition')));
    }

    public function test_area_id_filters_to_only_that_areas_items(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        $areaAId = $items[0]->area_id;

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-entry-sheet?area_id={$areaAId}");

        $response->assertOk();
        // Area A has Item One + Item Three -- "2-items" proves the area filter narrowed
        // the query independently of phase.
        $this->assertStringContainsString('2-items', urldecode($response->headers->get('content-disposition')));
    }

    public function test_item_ids_and_phase_id_combine_as_an_intersection(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        [$one, $two, $three] = $items;
        $phase1Id = $one->phase_id;

        // item_ids picks {one, three}, but phase_id narrows to phase 1 (only "one"
        // actually belongs to it) -- the combined result must be just the one item.
        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-entry-sheet?item_ids={$one->id},{$three->id}&phase_id={$phase1Id}");

        $response->assertOk();
        $this->assertStringContainsString('1-items', urldecode($response->headers->get('content-disposition')));
    }

    public function test_result_declaration_sheet_supports_the_same_bulk_filters(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();
        [$one, , $three] = $items;

        $response = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/result-declaration-sheet?item_ids={$one->id},{$three->id}");

        $response->assertOk();
        $this->assertStringContainsString('2-items', urldecode($response->headers->get('content-disposition')));
    }

    /** Existing single-item and whole-event behavior must be unaffected by the new bulk params. */
    public function test_existing_single_item_and_whole_event_behavior_is_unchanged(): void
    {
        [$sahodaya, $event, $admin, $items] = $this->fixture();

        $single = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-entry-sheet?item_id={$items[0]->id}");
        $single->assertOk();
        $this->assertStringContainsString('item-one', urldecode($single->headers->get('content-disposition')));
        $this->assertStringNotContainsString('items', str_replace('item-one', '', urldecode($single->headers->get('content-disposition'))));

        $whole = $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/mark-entry-sheet");
        $whole->assertOk();
    }
}

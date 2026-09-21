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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The bulk combined-PDF sheet picker (Judge Sheets/Digital Sum Sheet/Result Declaration
 * Sheet, multi-item + phase/area filters) moved from a collapsible panel inside Mark Entry
 * to its own page/tab -- FestMarkEntryController::bulkSheets(). The actual PDF endpoints
 * (markEntrySheet/cumulativeSheet/resultDeclarationSheet) are unchanged and already covered
 * by FestMarkEntrySheetBulkTest; this only covers the new page's own props.
 */
class FestBulkSheetsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_sheets_page_lists_items_phases_areas_and_saved_combo(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Sheets Page Sahodaya',
            'domain' => 'bulk-sheets-page-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id, 'prefix' => 'BSP', 'student_data_mode' => 'counts_only',
            'bulk_report_combo' => ['judge_sheet', 'sum_sheet'],
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Sheets Page Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $phase = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Phase 1', 'code' => 'P1', 'sort_order' => 1]);
        $area = FestCompetitionArea::create(['tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'name' => 'Area A']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item One', 'participant_type' => 'individual', 'is_enabled' => true]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/bulk-sheets")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/BulkSheets', false)
                ->where('items', fn ($items) => collect($items)->pluck('title')->contains('Item One'))
                ->where('phases', fn ($phases) => collect($phases)->pluck('name')->contains('Phase 1'))
                ->where('competitionAreas', fn ($areas) => collect($areas)->pluck('name')->contains('Area A'))
                ->where('bulkReportCombo', ['judge_sheet', 'sum_sheet']));
    }
}

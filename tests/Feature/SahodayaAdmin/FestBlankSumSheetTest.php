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
 * A genuinely blank paper Sum Sheet -- Sl No, Chest No, one column per judge, Grand
 * Total, every cell empty -- distinct from cumulativeSheet()'s "Digital Sum Sheet",
 * which shows the real marks already entered online. Only multi-judge items have a
 * sum sheet at all (nothing to add up for a single judge).
 */
class FestBlankSumSheetTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: FestEventItem, 4: FestEventItem} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Blank Sum Sheet Sahodaya',
            'domain' => 'blank-sum-sheet-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BSS', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Blank Sum Sheet Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $multiJudgeItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Classical Dance', 'participant_type' => 'individual',
            'is_enabled' => true, 'mark_judge_count' => 3,
        ]);
        $singleJudgeItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Elocution', 'participant_type' => 'individual',
            'is_enabled' => true, 'mark_judge_count' => 1,
        ]);

        return [$sahodaya, $event, $admin, $multiJudgeItem, $singleJudgeItem];
    }

    public function test_downloads_a_blank_sum_sheet_for_a_multi_judge_item(): void
    {
        [$sahodaya, $event, $admin, $multiJudgeItem] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/sum-sheet?item_id={$multiJudgeItem->id}"
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_single_judge_item_alone_returns_404(): void
    {
        [$sahodaya, $event, $admin, , $singleJudgeItem] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/sum-sheet?item_id={$singleJudgeItem->id}"
        );

        $response->assertNotFound();
    }

    public function test_bulk_selection_silently_drops_single_judge_items_and_keeps_multi_judge_ones(): void
    {
        [$sahodaya, $event, $admin, $multiJudgeItem, $singleJudgeItem] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/sum-sheet?item_ids={$multiJudgeItem->id},{$singleJudgeItem->id}"
        );

        $response->assertOk();
    }
}

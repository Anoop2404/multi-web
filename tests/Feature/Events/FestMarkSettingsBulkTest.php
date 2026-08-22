<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestMarkCriteriaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bulk Total Marks / Judge Count editor — set once, apply to every item in the
 * event, instead of opening each of N items individually (see Items → Bulk
 * Limit Caps for the identical shape this was modeled on).
 */
class FestMarkSettingsBulkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, event: FestEvent, items: array<FestEventItem>} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Bulk Mark Settings Sahodaya',
            'domain' => 'bulk-mark-settings.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BM', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Bulk Mark Settings Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);

        $items = [
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'item_code' => 'A', 'category' => 'literary']),
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item B', 'item_code' => 'B', 'category' => 'literary']),
            FestEventItem::create(['event_id' => $event->id, 'title' => 'Item C', 'item_code' => 'C', 'category' => 'literary']),
        ];

        return compact('sahodaya', 'admin', 'event', 'items');
    }

    public function test_bulk_update_sets_total_marks_and_judge_count_for_every_listed_item(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'items' => $items] = $this->fixture();

        $response = $this->actingAs($admin)->post(route('sahodaya.events.mark-settings.bulk-update', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'items' => [
                ['id' => $items[0]->id, 'total_marks' => 100, 'judge_count' => 2],
                ['id' => $items[1]->id, 'total_marks' => 50, 'judge_count' => 1],
                ['id' => $items[2]->id, 'total_marks' => null, 'judge_count' => 3],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();

        $criteriaService = app(FestMarkCriteriaService::class);

        $itemA = $items[0]->fresh();
        $this->assertSame('100.00', $itemA->total_marks);
        $this->assertSame(2, $criteriaService->judgeCountForItem($itemA));

        $itemB = $items[1]->fresh();
        $this->assertSame('50.00', $itemB->total_marks);
        $this->assertSame(1, $criteriaService->judgeCountForItem($itemB));

        $itemC = $items[2]->fresh();
        $this->assertNull($itemC->total_marks);
        $this->assertSame(3, $criteriaService->judgeCountForItem($itemC));
    }

    public function test_bulk_update_only_affects_items_from_this_event(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'items' => $items] = $this->fixture();

        $otherEvent = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Other Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);
        $foreignItem = FestEventItem::create(['event_id' => $otherEvent->id, 'title' => 'Foreign Item', 'item_code' => 'F']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.mark-settings.bulk-update', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'items' => [
                ['id' => $items[0]->id, 'total_marks' => 100, 'judge_count' => 1],
                ['id' => $foreignItem->id, 'total_marks' => 999, 'judge_count' => 1],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('100.00', $items[0]->fresh()->total_marks);
        $this->assertNull($foreignItem->fresh()->total_marks, 'An item belonging to a different event must not be updated.');
    }

    public function test_bulk_settings_page_lists_every_item_with_its_current_settings(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'event' => $event, 'items' => $items] = $this->fixture();
        app(FestMarkCriteriaService::class)->setJudgeCount($items[0], 4);
        $items[0]->update(['total_marks' => 75]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.mark-settings.bulk', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]));

        $response->assertOk();
        $rows = collect($response->viewData('page')['props']['items']);
        $this->assertCount(3, $rows);

        $rowA = $rows->firstWhere('id', $items[0]->id);
        $this->assertSame('75.00', $rowA['total_marks']);
        $this->assertSame(4, $rowA['judge_count']);
    }
}

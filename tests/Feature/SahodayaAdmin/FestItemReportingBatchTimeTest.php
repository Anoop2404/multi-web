<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemReportingBatch;
use App\Models\FestItemReportingBatchTime;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FestItemReportingBatchTimeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Batch Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BT', 'student_data_mode' => 'counts_only']);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return [$sahodaya, $admin];
    }

    private function makeEvent(string $sahodayaId): FestEvent
    {
        return FestEvent::create([
            'tenant_id' => $sahodayaId,
            'title' => 'Batch Test Event '.Str::random(4),
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);
    }

    public function test_updating_batch_time_for_one_item_does_not_affect_other_items(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);

        $itemA = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Bharatanatyam',
            'item_code' => 'BN01',
            'category' => 'dance',
            'gender' => 'open',
        ]);

        $itemB = FestEventItem::create([
            'event_id' => $event->id,
            'title' => 'Mohiniyattam',
            'item_code' => 'MY01',
            'category' => 'dance',
            'gender' => 'open',
        ]);

        $batch1 = FestItemReportingBatch::create([
            'event_id' => $event->id,
            'label' => 'Batch 1',
            'sort_order' => 1,
        ]);

        $batch2 = FestItemReportingBatch::create([
            'event_id' => $event->id,
            'label' => 'Batch 2',
            'sort_order' => 2,
        ]);

        // 1. Update Batch 1 report time for Item A
        $timeA = '2026-11-20 09:30:00';
        $response = $this->actingAs($admin)->put(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reporting-batches/{$batch1->id}",
            [
                'item_id' => $itemA->id,
                'report_at' => $timeA,
            ]
        );

        $response->assertRedirect();

        // Check FestItemReportingBatchTime record was created for Item A
        $this->assertDatabaseHas('fest_item_reporting_batch_times', [
            'event_id' => $event->id,
            'item_id' => $itemA->id,
            'batch_id' => $batch1->id,
        ]);

        // Batch table's shared report_at should NOT be overwritten with Item A's time
        $this->assertNull($batch1->fresh()->report_at);

        // Check Item B does not have any reporting batch time record
        $this->assertDatabaseMissing('fest_item_reporting_batch_times', [
            'item_id' => $itemB->id,
            'batch_id' => $batch1->id,
        ]);

        // 2. Fetch page for Item A — batches prop should show timeA for Batch 1
        $this->actingAs($admin)->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reporting-batches?item_id={$itemA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Sahodaya/Events/ReportingBatches')
                ->has('batches', 2)
                ->where('batches.0.id', $batch1->id)
                ->where('batches.0.report_at', fn ($val) => str_starts_with($val, '2026-11-20'))
            );

        // 3. Fetch page for Item B — batches prop should show report_at = null for Batch 1
        $this->actingAs($admin)->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reporting-batches?item_id={$itemB->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Sahodaya/Events/ReportingBatches')
                ->has('batches', 2)
                ->where('batches.0.id', $batch1->id)
                ->where('batches.0.report_at', null)
            );

        // 4. Now assign Item B's Batch 1 to a different time
        $timeB = '2026-11-21 14:00:00';
        $this->actingAs($admin)->put(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reporting-batches/{$batch1->id}",
            [
                'item_id' => $itemB->id,
                'report_at' => $timeB,
            ]
        )->assertRedirect();

        // 5. Verify both items retain their distinct times
        $recordA = FestItemReportingBatchTime::where('item_id', $itemA->id)->where('batch_id', $batch1->id)->first();
        $recordB = FestItemReportingBatchTime::where('item_id', $itemB->id)->where('batch_id', $batch1->id)->first();

        $this->assertNotNull($recordA);
        $this->assertNotNull($recordB);
        $this->assertStringStartsWith('2026-11-20', $recordA->report_at->toDateTimeString());
        $this->assertStringStartsWith('2026-11-21', $recordB->report_at->toDateTimeString());
    }
}

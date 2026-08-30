<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestItemScheduleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for FestItemScheduleService (item-level, no-participant scheduling) and the
 * FestScheduleController endpoints that delegate to it: itemsIndex, bulkStoreItems,
 * itemImportTemplate, itemImportStore.
 */
class FestItemScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Item Schedule Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'ISC', 'student_data_mode' => 'counts_only']);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return [$sahodaya, $admin];
    }

    private function makeEvent(string $sahodayaId): FestEvent
    {
        return FestEvent::create([
            'tenant_id' => $sahodayaId,
            'title' => 'Item Schedule Test Event '.Str::random(4),
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);
    }

    private function makeItem(FestEvent $event, string $title = 'Group Song'): FestEventItem
    {
        return FestEventItem::create([
            'event_id' => $event->id,
            'title' => $title,
            'owner_level' => 'sahodaya',
            'is_enabled' => true,
        ]);
    }

    // ── bulkSave(): empty submission clears an existing row ─────────────

    public function test_bulk_save_deletes_the_existing_item_level_row_when_submitted_with_no_date_stage_or_time(): void
    {
        [$sahodaya] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);

        FestSchedule::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'participant_id' => null,
            'scheduled_at' => now()->addDay(),
            'stage' => 'Main Stage',
            'sort_order' => 1,
        ]);
        $this->assertSame(1, FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->count());

        $saved = app(FestItemScheduleService::class)->bulkSave($event, [[
            'item_id' => $item->id,
            'scheduled_at' => '',
            'scheduled_date' => '',
            'scheduled_time' => '',
            'stage_id' => null,
            'stage' => '',
        ]]);

        $this->assertSame(0, $saved, 'a clearing row is not counted as saved');
        $this->assertSame(0, FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->count());
    }

    public function test_bulk_save_creates_a_row_when_data_is_present(): void
    {
        [$sahodaya] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Main Stage']);

        $saved = app(FestItemScheduleService::class)->bulkSave($event, [[
            'item_id' => $item->id,
            'scheduled_date' => '2026-09-02',
            'scheduled_time' => '14:30',
            'stage_id' => $stage->id,
        ]]);

        $this->assertSame(1, $saved);
        $schedule = FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-09-02 14:30:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($stage->id, $schedule->stage_id);
        $this->assertSame('Main Stage', $schedule->stage);
    }

    // ── resolveDateTime(): both input shapes ────────────────────────────

    public function test_bulk_save_accepts_a_combined_scheduled_at_field(): void
    {
        [$sahodaya] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);

        app(FestItemScheduleService::class)->bulkSave($event, [[
            'item_id' => $item->id,
            'scheduled_at' => '2026-09-03 09:15:00',
        ]]);

        $schedule = FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-09-03 09:15:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_bulk_save_accepts_separate_scheduled_date_and_time_fields(): void
    {
        [$sahodaya] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);

        app(FestItemScheduleService::class)->bulkSave($event, [[
            'item_id' => $item->id,
            'scheduled_date' => '2026-09-04',
            'scheduled_time' => '11:00',
        ]]);

        $schedule = FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-09-04 11:00:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_bulk_store_items_endpoint_accepts_the_combined_scheduled_at_shape(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.items.bulk', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'rows' => [[
                'item_id' => $item->id,
                'scheduled_at' => '2026-09-05 08:00:00',
            ]],
        ]);

        $response->assertSessionHas('success');
        $schedule = FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-09-05 08:00:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_bulk_store_items_endpoint_accepts_the_separate_date_and_time_shape(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.items.bulk', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'rows' => [[
                'item_id' => $item->id,
                'scheduled_date' => '2026-09-06',
                'scheduled_time' => '16:45',
            ]],
        ]);

        $response->assertSessionHas('success');
        $schedule = FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-09-06 16:45:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
    }

    // ── rowsForEvent() / summary() via itemsIndex ───────────────────────

    public function test_items_index_reports_scheduled_and_unscheduled_counts(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $scheduledItem = $this->makeItem($event, 'Scheduled Item');
        $this->makeItem($event, 'Unscheduled Item');
        FestSchedule::create([
            'event_id' => $event->id,
            'item_id' => $scheduledItem->id,
            'participant_id' => null,
            'scheduled_at' => now()->addDay(),
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.schedule.items', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $summary = app(FestItemScheduleService::class)->summary($event);
        $this->assertSame(['total' => 2, 'scheduled' => 1, 'unscheduled' => 1], $summary);
    }

    // ── CSV import ──────────────────────────────────────────────────────

    public function test_item_import_creates_a_schedule_row_from_a_valid_csv_row(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Main Stage']);

        $csv = "item_id,item_title,scheduled_date,scheduled_time,stage,sort_order\n"
            ."{$item->id},{$item->title},2026-09-07,10:00,Main Stage,3\n";
        $file = UploadedFile::fake()->createWithContent('item-schedule.csv', $csv);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.items.import', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['file' => $file]);

        $response->assertSessionHas('success');
        $schedule = FestSchedule::where('item_id', $item->id)->whereNull('participant_id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-09-07 10:00:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($stage->id, $schedule->stage_id);
    }

    public function test_item_import_reports_a_per_row_error_for_an_unknown_item(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);

        $csv = "item_id,item_title,scheduled_date,scheduled_time,stage,sort_order\n"
            .",Nonexistent Item,2026-09-07,10:00,,1\n";
        $file = UploadedFile::fake()->createWithContent('item-schedule.csv', $csv);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.items.import', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['file' => $file]);

        $response->assertSessionHas('importErrors', function (array $errors) {
            return count($errors) === 1 && str_contains($errors[0], 'Unknown item');
        });
        $this->assertSame(0, FestSchedule::where('event_id', $event->id)->count());
    }
}

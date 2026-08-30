<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for FestScheduleController: participant-level schedule CRUD, auto-generation
 * from chest numbers, publish/unpublish gating (empty schedule + clash detection), reorder
 * swap semantics, and CSV import happy/error paths.
 *
 * Item-level (no-participant) scheduling — FestItemScheduleService — is covered separately
 * in tests/Feature/Events/FestItemScheduleServiceTest.php.
 */
class FestScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Schedule Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SCH', 'student_data_mode' => 'counts_only']);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return [$sahodaya, $admin];
    }

    private function makeEvent(string $sahodayaId): FestEvent
    {
        return FestEvent::create([
            'tenant_id' => $sahodayaId,
            'title' => 'Schedule Test Event '.Str::random(4),
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

    /** @param array<string, mixed> $overrides */
    private function makeApprovedParticipant(FestEvent $event, FestEventItem $item, array $overrides = []): FestParticipant
    {
        $registration = FestRegistration::create(array_merge([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => (string) Str::uuid(),
            'status' => 'approved',
            'submitted_at' => now(),
        ], $overrides['registration'] ?? []));

        return FestParticipant::create(array_merge([
            'registration_id' => $registration->id,
            'participant_type' => 'student',
            'participant_role' => 'performer',
        ], $overrides['participant'] ?? []));
    }

    // ── store() validation ──────────────────────────────────────────────

    public function test_store_creates_a_schedule_slot_for_a_valid_participant(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item);
        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Main Stage']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'stage_id' => $stage->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(1, FestSchedule::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('participant_id', $participant->id)
            ->count());
        $schedule = FestSchedule::where('participant_id', $participant->id)->first();
        $this->assertSame($stage->id, $schedule->stage_id);
        $this->assertSame('Main Stage', $schedule->stage);
    }

    public function test_store_rejects_a_participant_not_registered_for_the_given_item(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $itemA = $this->makeItem($event, 'Item A');
        $itemB = $this->makeItem($event, 'Item B');
        $participant = $this->makeApprovedParticipant($event, $itemA);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'item_id' => $itemB->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FestSchedule::where('participant_id', $participant->id)->count());
    }

    public function test_store_rejects_a_participant_whose_registration_is_not_approved(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item, [
            'registration' => ['status' => 'submitted'],
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FestSchedule::where('participant_id', $participant->id)->count());
    }

    public function test_store_rejects_a_standby_participant(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item, [
            'participant' => ['participant_role' => 'standby'],
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FestSchedule::where('participant_id', $participant->id)->count());
    }

    public function test_store_rejects_a_disqualified_participant(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item, [
            'participant' => ['disqualified_at' => now()],
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), [
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FestSchedule::where('participant_id', $participant->id)->count());
    }

    // ── autoGenerate() ──────────────────────────────────────────────────

    public function test_auto_generate_orders_schedule_rows_by_chest_number(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);

        $third = $this->makeApprovedParticipant($event, $item, ['participant' => ['chest_no' => 30]]);
        $first = $this->makeApprovedParticipant($event, $item, ['participant' => ['chest_no' => 10]]);
        $second = $this->makeApprovedParticipant($event, $item, ['participant' => ['chest_no' => 20]]);

        // A standby and a disqualified participant with low chest numbers must be excluded
        // from auto-generation entirely, not merely sorted first.
        $standby = $this->makeApprovedParticipant($event, $item, ['participant' => ['chest_no' => 1, 'participant_role' => 'standby']]);
        $disqualified = $this->makeApprovedParticipant($event, $item, ['participant' => ['chest_no' => 2, 'disqualified_at' => now()]]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.auto', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertSessionHas('success');

        $this->assertSame(1, FestSchedule::where('participant_id', $first->id)->value('sort_order'));
        $this->assertSame(2, FestSchedule::where('participant_id', $second->id)->value('sort_order'));
        $this->assertSame(3, FestSchedule::where('participant_id', $third->id)->value('sort_order'));
        $this->assertSame(0, FestSchedule::where('participant_id', $standby->id)->count());
        $this->assertSame(0, FestSchedule::where('participant_id', $disqualified->id)->count());
    }

    // ── publish / unpublish ─────────────────────────────────────────────

    public function test_publish_fails_when_there_are_no_schedule_rows(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.publish', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertStatus(422);
        $this->assertFalse($event->fresh()->schedule_published);
    }

    public function test_publish_fails_when_a_stage_conflict_exists(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $itemA = $this->makeItem($event, 'Item A');
        $itemB = $this->makeItem($event, 'Item B');
        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Main Stage']);
        $at = now()->addDay()->startOfHour();

        // Two different items scheduled on the same stage at overlapping times (default
        // 60-minute duration since neither item sets duration_minutes) — a stage conflict
        // that publishSchedule() must block on, independent of any student clash.
        FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemA->id, 'scheduled_at' => $at, 'stage_id' => $stage->id, 'sort_order' => 1]);
        FestSchedule::create(['event_id' => $event->id, 'item_id' => $itemB->id, 'scheduled_at' => $at, 'stage_id' => $stage->id, 'sort_order' => 2]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.publish', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertStatus(422);
        $this->assertFalse($event->fresh()->schedule_published);
    }

    public function test_publish_succeeds_with_valid_non_clashing_rows_and_sets_schedule_published(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item);
        FestSchedule::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay(),
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.publish', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertSessionHas('success');
        $this->assertTrue($event->fresh()->schedule_published);
    }

    public function test_unpublish_reverses_a_published_schedule(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item);
        FestSchedule::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay(),
            'sort_order' => 1,
        ]);
        $this->actingAs($admin)->post(route('sahodaya.events.schedule.publish', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));
        $this->assertTrue($event->fresh()->schedule_published);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.unpublish', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertSessionHas('success');
        $this->assertFalse($event->fresh()->schedule_published);
    }

    public function test_publish_fails_when_the_schedule_is_already_published(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item);
        FestSchedule::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'participant_id' => $participant->id,
            'scheduled_at' => now()->addDay(),
            'sort_order' => 1,
        ]);
        $event->update(['schedule_published' => true]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.publish', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertStatus(422);
    }

    // ── reorder() ───────────────────────────────────────────────────────

    public function test_reorder_up_swaps_sort_order_with_the_previous_row(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $a = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 1]);
        $b = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 2]);
        $c = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 3]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.reorder', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'schedule' => $b->id,
        ]), ['direction' => 'up']);

        $response->assertSessionHas('success');
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(3, $c->fresh()->sort_order);
    }

    public function test_reorder_down_swaps_sort_order_with_the_next_row(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $a = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 1]);
        $b = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 2]);
        $c = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 3]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.reorder', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'schedule' => $b->id,
        ]), ['direction' => 'down']);

        $response->assertSessionHas('success');
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
        $this->assertSame(2, $c->fresh()->sort_order);
    }

    public function test_reorder_does_not_affect_rows_belonging_to_a_different_event(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $a = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 1]);
        $b = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 2]);

        $otherEvent = $this->makeEvent($sahodaya->id);
        $otherItem = $this->makeItem($otherEvent, 'Other Event Item');
        $unrelated = FestSchedule::create(['event_id' => $otherEvent->id, 'item_id' => $otherItem->id, 'sort_order' => 2]);

        $this->actingAs($admin)->post(route('sahodaya.events.schedule.reorder', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'schedule' => $b->id,
        ]), ['direction' => 'up']);

        $this->assertSame(2, $unrelated->fresh()->sort_order);
    }

    // ── destroy() ───────────────────────────────────────────────────────

    public function test_destroy_deletes_a_schedule_row(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $schedule = FestSchedule::create(['event_id' => $event->id, 'item_id' => $item->id, 'sort_order' => 1]);

        $response = $this->actingAs($admin)->delete(route('sahodaya.events.schedule.destroy', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'schedule' => $schedule->id,
        ]));

        $response->assertSessionHas('success');
        $this->assertNull(FestSchedule::find($schedule->id));
    }

    public function test_destroy_returns_404_for_a_schedule_row_from_a_different_event(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $otherEvent = $this->makeEvent($sahodaya->id);
        $otherItem = $this->makeItem($otherEvent, 'Other Event Item');
        $schedule = FestSchedule::create(['event_id' => $otherEvent->id, 'item_id' => $otherItem->id, 'sort_order' => 1]);

        $response = $this->actingAs($admin)->delete(route('sahodaya.events.schedule.destroy', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'schedule' => $schedule->id,
        ]));

        $response->assertStatus(404);
        $this->assertNotNull(FestSchedule::find($schedule->id));
    }

    // ── CSV import ──────────────────────────────────────────────────────

    public function test_import_creates_a_schedule_row_from_a_valid_csv_row(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);
        $item = $this->makeItem($event);
        $participant = $this->makeApprovedParticipant($event, $item);
        $stage = FestStage::create(['event_id' => $event->id, 'name' => 'Main Stage']);

        $csv = "item_id,participant_id,scheduled_at,stage,sort_order\n"
            ."{$item->id},{$participant->id},2026-09-01 10:00:00,Main Stage,5\n";
        $file = UploadedFile::fake()->createWithContent('schedule.csv', $csv);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.import', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['file' => $file]);

        $response->assertSessionHas('success');
        $schedule = FestSchedule::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('participant_id', $participant->id)
            ->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-09-01 10:00:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($stage->id, $schedule->stage_id);
        $this->assertSame(5, $schedule->sort_order);
    }

    public function test_import_reports_a_per_row_error_for_an_unknown_item(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = $this->makeEvent($sahodaya->id);

        $csv = "item_id,participant_id,scheduled_at,stage,sort_order\n"
            ."999999,,2026-09-01 10:00:00,,1\n";
        $file = UploadedFile::fake()->createWithContent('schedule.csv', $csv);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.schedule.import', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['file' => $file]);

        $response->assertSessionHas('importErrors', function (array $errors) {
            return count($errors) === 1 && str_contains($errors[0], 'Unknown item');
        });
        $this->assertSame(0, FestSchedule::where('event_id', $event->id)->count());
    }
}

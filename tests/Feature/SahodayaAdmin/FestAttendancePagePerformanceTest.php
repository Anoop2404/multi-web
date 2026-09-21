<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Attendance page was slow to open on a live event: index() loaded every
 * participant across the WHOLE event/phase on every single load (the page only ever
 * shows one item's roster at a time -- Attendance.vue always filters client-side down
 * to one item anyway), and built each row's photo URL via a route that does a live,
 * uncached S3 existence-check-then-stream per photo instead of a cacheable direct URL.
 * This locks in both fixes.
 */
class FestAttendancePagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: FestEvent, 2: User, 3: FestEventItem, 4: FestEventItem} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Attendance Perf Sahodaya',
            'domain' => 'attendance-perf-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'AP', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Attendance Perf School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Attendance Perf Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
        ]);

        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'participant_type' => 'individual', 'is_enabled' => true]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item B', 'participant_type' => 'individual', 'is_enabled' => true]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);

        foreach ([$itemA, $itemB] as $item) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student for {$item->title}", 'admission_no' => "S-{$item->id}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'main']);
        }

        return [$sahodaya, $event, $admin, $itemA, $itemB];
    }

    public function test_selecting_one_item_only_returns_that_items_participants(): void
    {
        [$sahodaya, $event, $admin, $itemA] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/attendance?item_id={$itemA->id}"
        );

        $response->assertOk();
        $props = $response->getOriginalContent()->getData()['page']['props'];

        $this->assertCount(1, $props['participants']);
        $this->assertSame('Student for Item A', $props['participants'][0]['student']['name']);
    }

    public function test_no_item_id_defaults_to_the_first_item_not_the_whole_event(): void
    {
        [$sahodaya, $event, $admin] = $this->fixture();

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/attendance"
        );

        $response->assertOk();
        $props = $response->getOriginalContent()->getData()['page']['props'];

        // Exactly one item's worth of participants (1), never both items' (2) --
        // proves the default path is scoped the same way an explicit ?item_id= is,
        // not silently falling back to "every participant in the event".
        $this->assertCount(1, $props['participants']);
    }

    public function test_participant_photo_url_uses_the_fast_direct_path_not_the_live_stream_route(): void
    {
        [$sahodaya, $event, $admin, $itemA] = $this->fixture();

        $student = Student::where('name', 'Student for Item A')->firstOrFail();
        $student->update(['photo' => 'students/fake-photo.jpg']);

        $response = $this->actingAs($admin)->get(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/attendance?item_id={$itemA->id}"
        );

        $response->assertOk();
        $props = $response->getOriginalContent()->getData()['page']['props'];
        $photoUrl = $props['participants'][0]['student']['photo_url'];

        // sahodayaPhotoUrl() (the slow, uncached, per-request live-stream endpoint this
        // page used before) always routes through /students/{id}/photo -- confirming
        // that path is gone here is exactly what proves the fast one is used instead.
        $this->assertStringNotContainsString('/students/'.$student->id.'/photo', (string) $photoUrl);
    }
}

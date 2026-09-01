<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The "Save All" button on the Mark Entry page used to POST one participant at a time
 * (see MarkEntry.vue's saveAll(), before it was rewritten to call this endpoint) — each
 * of those requests independently ran the event's entire points recalculation
 * (EventContext::recalculateSchoolPoints() rescans every FestMark in the event), so
 * saving N participants meant N full recalculations for what the admin experienced as
 * one click. FestMarkEntryController::bulkStore() saves every row with
 * recalculate: false and recalculates once at the end — this test proves the fix the
 * same way FestMarkEntryTeamSaveRecalculationTest does for team-expansion, and also
 * confirms one bad row doesn't sink the rest of the batch.
 */
class FestMarkEntryBulkSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_save_writes_every_row_and_recalculates_only_once(): void
    {
        Event::fake([FestScoreboardUpdated::class]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Mark Save Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BM', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Bulk Mark Save School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Bulk Mark Save Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $itemA = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SS1',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $itemB = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Dance', 'item_code' => 'SD1',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);

        $makeParticipant = function (FestEventItem $item) use ($event, $school, $schoolClass) {
            static $i = 0;
            $i++;
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Student {$i}", 'reg_no' => "STU/{$i}"]);
            $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);

            return FestParticipant::create([
                'registration_id' => $registration->id, 'student_id' => $student->id,
                'participant_type' => 'student', 'event_id' => $event->id, 'participant_role' => 'main',
            ]);
        };

        $good1 = $makeParticipant($itemA);
        $good2 = $makeParticipant($itemB);

        // A disqualified participant makes FestMarkSaveService::save() abort with a 422 —
        // this row must fail without affecting the other two.
        $bad = $makeParticipant($itemA);
        $bad->update(['disqualified_at' => now()]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $event->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.marks.bulk-store', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'rows' => [
                ['participant_id' => $good1->id, 'item_id' => $itemA->id, 'grade' => 'A', 'position' => 1],
                ['participant_id' => $good2->id, 'item_id' => $itemB->id, 'grade' => 'B', 'position' => 2],
                ['participant_id' => $bad->id, 'item_id' => $itemA->id, 'grade' => 'A', 'position' => 3],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('fest_marks', ['participant_id' => $good1->id, 'item_id' => $itemA->id, 'grade' => 'A']);
        $this->assertDatabaseHas('fest_marks', ['participant_id' => $good2->id, 'item_id' => $itemB->id, 'grade' => 'B']);
        $this->assertDatabaseMissing('fest_marks', ['participant_id' => $bad->id]);

        $result = session('bulkMarkSaveResult');
        $this->assertSame(2, $result['saved_count']);
        $this->assertSame(3, $result['total']);
        $this->assertTrue($result['results'][$good1->id]['ok']);
        $this->assertTrue($result['results'][$good2->id]['ok']);
        $this->assertFalse($result['results'][$bad->id]['ok']);

        // The dominant cost this endpoint exists to fix: one recalculation for the whole
        // batch, not one per saved row.
        Event::assertDispatchedTimes(FestScoreboardUpdated::class, 1);
    }
}

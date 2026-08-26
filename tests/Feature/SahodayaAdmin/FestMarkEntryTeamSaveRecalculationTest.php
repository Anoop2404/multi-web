<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestGroup;
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
 * Regression for a reported "team/group items take a long time to save" performance bug.
 * FestMarkEntryController::store() expands one team-item save into N participant saves
 * (one FestMark row per member — see expandToTeam()), and FestMarkSaveService::save()
 * used to run the event's ENTIRE points recalculation (EventContext::recalculateSchoolPoints(),
 * which rescans every FestMark in the event) plus a scoreboard broadcast on every single
 * one of those N iterations. A 3-member team save therefore did the expensive whole-event
 * recompute 3 times for what the admin experienced as one click. Fixed by only
 * recalculating once after the whole batch (recalculate: false inside the loop, one
 * explicit recalculate() call after it) — this test proves the fix by asserting the
 * scoreboard-updated broadcast fires exactly once, not once per team member, while still
 * confirming every team member actually got their own FestMark row.
 */
class FestMarkEntryTeamSaveRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_one_team_items_mark_recalculates_the_scoreboard_only_once(): void
    {
        Event::fake([FestScoreboardUpdated::class]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Team Mark Save Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'TM',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Team Mark Save School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Team Mark Save Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Group Dance', 'item_code' => 'GD1',
            'stage_type' => 'on_stage', 'participant_type' => 'team', 'category' => 'dance', 'is_enabled' => true,
        ]);

        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $group = FestGroup::create(['registration_id' => $registration->id, 'event_id' => $event->id, 'team_name' => 'Team A', 'status' => 'active']);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $participantIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => "Team Member {$i}", 'reg_no' => "STU/TEAM/{$i}"]);
            $participant = FestParticipant::create([
                'registration_id' => $registration->id, 'group_id' => $group->id, 'student_id' => $student->id,
                'participant_type' => 'student', 'event_id' => $event->id,
            ]);
            $participantIds[] = $participant->id;
        }

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $event->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.marks.store', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'participant_id' => $participantIds[0],
            'item_id' => $item->id,
            'grade' => 'A',
            'position' => 1,
        ]);

        $response->assertRedirect();

        $this->assertSame(3, FestMark::where('item_id', $item->id)->count(), 'every team member must get their own FestMark row');
        foreach ($participantIds as $participantId) {
            $this->assertDatabaseHas('fest_marks', ['item_id' => $item->id, 'participant_id' => $participantId, 'grade' => 'A']);
        }

        Event::assertDispatchedTimes(FestScoreboardUpdated::class, 1);
    }
}

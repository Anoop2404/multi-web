<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The new admin "manage participants" endpoints (eligible-students / add / remove) live in
 * the same SahodayaAdmin routes/controller as approve/reject/substitute, which are already
 * scoped by EnsureSahodayaAdmin + EventRegionAdminScope off FestEventStaff.region_id — no new
 * region-scoping code was written for them. This confirms that existing scoping actually
 * covers the new routes too, rather than assuming it does.
 */
class FestRegistrationParticipantRosterRegionScopeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'     => (string) Str::uuid(),
            'type'   => 'sahodaya',
            'name'   => 'Roster Scope Sahodaya',
            'domain' => 'roster-scope-'.Str::random(8).'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'school',
            'name'      => 'Roster Scope School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RA']);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RB']);

        $hub = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Roster Scope Hub',
            'event_type'   => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'status'       => 'active',
        ]);

        $regionAEvent = FestEvent::create([
            'tenant_id'       => $sahodaya->id,
            'title'           => 'Roster Scope Hub — Region A',
            'event_type'      => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'region_id'       => $regionA->id,
            'status'          => 'active',
        ]);

        $regionBEvent = FestEvent::create([
            'tenant_id'       => $sahodaya->id,
            'title'           => 'Roster Scope Hub — Region B',
            'event_type'      => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'region_id'       => $regionB->id,
            'status'          => 'active',
        ]);

        $item = FestEventItem::create([
            'event_id'         => $regionAEvent->id,
            'title'            => 'Roster Scope Item',
            'category'         => 'literary',
            'participant_type' => 'individual',
            'is_enabled'       => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '8']);

        $registration = FestRegistration::create([
            'event_id'  => $regionAEvent->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'approved',
            'mode'      => 'full',
        ]);

        $performer = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Performer One']);
        FestParticipant::create([
            'registration_id'  => $registration->id,
            'event_id'         => $regionAEvent->id,
            'student_id'       => $performer->id,
            'participant_type' => 'student',
            'participant_role' => 'performer',
        ]);

        // A second participant so removeParticipant() — which refuses to strip a registration
        // down to zero participants — has something safe to remove in the "allowed" case.
        $standby = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Standby One']);
        $standbyParticipant = FestParticipant::create([
            'registration_id'  => $registration->id,
            'event_id'         => $regionAEvent->id,
            'student_id'       => $standby->id,
            'participant_type' => 'student',
            'participant_role' => 'standby',
        ]);

        $regionAAdmin = $this->makeRegionAdmin($sahodaya, $regionAEvent, $regionA);
        $regionBAdmin = $this->makeRegionAdmin($sahodaya, $regionBEvent, $regionB);

        return compact('sahodaya', 'school', 'regionAEvent', 'regionBEvent', 'registration', 'standbyParticipant', 'regionAAdmin', 'regionBAdmin');
    }

    /** Mirrors FestEventStaffController::store()'s region_admin branch. */
    private function makeRegionAdmin(Tenant $sahodaya, FestEvent $event, Region $region): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('region_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('region_admin'));

        FestEventStaff::create([
            'event_id'  => $event->id,
            'user_id'   => $admin->id,
            'duty'      => 'region_admin',
            'region_id' => $region->id,
        ]);

        return $admin;
    }

    public function test_region_admin_scoped_to_the_right_region_can_manage_participants(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['regionAAdmin'])
            ->getJson(route('sahodaya.events.registrations.eligible-students', [
                'tenantId'     => $f['sahodaya']->id,
                'event'        => $f['regionAEvent']->id,
                'registration' => $f['registration']->id,
            ]))
            ->assertOk();

        $this->actingAs($f['regionAAdmin'])
            ->delete(route('sahodaya.events.registrations.participants.destroy', [
                'tenantId'     => $f['sahodaya']->id,
                'event'        => $f['regionAEvent']->id,
                'registration' => $f['registration']->id,
                'participant'  => $f['standbyParticipant']->id,
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('fest_participants', ['id' => $f['standbyParticipant']->id]);
    }

    public function test_region_admin_scoped_to_a_different_region_is_forbidden(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['regionBAdmin'])
            ->getJson(route('sahodaya.events.registrations.eligible-students', [
                'tenantId'     => $f['sahodaya']->id,
                'event'        => $f['regionAEvent']->id,
                'registration' => $f['registration']->id,
            ]))
            ->assertForbidden();

        $this->actingAs($f['regionBAdmin'])
            ->delete(route('sahodaya.events.registrations.participants.destroy', [
                'tenantId'     => $f['sahodaya']->id,
                'event'        => $f['regionAEvent']->id,
                'registration' => $f['registration']->id,
                'participant'  => $f['standbyParticipant']->id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('fest_participants', ['id' => $f['standbyParticipant']->id]);
    }
}

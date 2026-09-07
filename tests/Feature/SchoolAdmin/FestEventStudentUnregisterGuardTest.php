<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestLevelRegistration;
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
 * Regression for a live bug: FestEventStudentRegistrationController::destroy()'s route
 * ({tenantId}/.../events/{event}/register-students/{student}, with a 'program' route
 * default) declared its $program and $student parameters in the wrong order relative to
 * how the router resolves them, so every call silently received $student = the program
 * slug (e.g. "custom") and $program = the actual numeric student id. Casting the slug to
 * int always produced 0, so FestEventRegistrationService::withdrawStudent()'s lookup never
 * matched anything and no-opped — yet the controller unconditionally flashed "Removed the
 * student's event registration." regardless, so every "Cancel" click on the school's Step 1
 * "Event Registration" list appeared to succeed while doing nothing at all.
 */
class FestEventStudentUnregisterGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent, student: Student} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Unregister Guard Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'UG', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Unregister Guard School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Guard Test Custom Event', 'event_type' => 'custom',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $schoolClass = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => '8'],
            ['display_order' => 1, 'is_active' => true],
        );
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'admission_number' => 'ADM1', 'reg_no' => 'REG1', 'name' => 'Guard Test Student', 'status' => 'active',
        ]);

        FestLevelRegistration::create([
            'event_id' => $event->id, 'student_id' => $student->id, 'school_id' => $school->id,
            'registration_number' => 'UG-0001', 'status' => 'active', 'registered_at' => now(),
        ]);

        return compact('sahodaya', 'school', 'admin', 'event', 'student');
    }

    public function test_the_destroy_route_receives_the_correct_student_id_not_the_program_slug(): void
    {
        ['school' => $school, 'admin' => $admin, 'event' => $event, 'student' => $student] = $this->fixture();

        $this->actingAs($admin)->delete(route('school.custom.event.register-students.destroy', [
            'tenantId' => $school->id, 'event' => $event->id, 'student' => $student->id,
        ]));

        // Confirms the parameter-order fix: withdrawStudent() must have actually found and
        // updated THIS student's row, which only happens if $student resolved to the real
        // numeric id rather than the "custom" program slug (which casts to int 0).
        $this->assertSame(
            'withdrawn',
            FestLevelRegistration::where('student_id', $student->id)->value('status'),
        );
    }

    public function test_unregister_is_blocked_while_the_student_has_an_active_item_registration(): void
    {
        ['school' => $school, 'admin' => $admin, 'event' => $event, 'student' => $student] = $this->fixture();

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Guard Test Item', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $itemRegistration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id,
            'status' => 'approved', 'submitted_at' => now(),
        ]);
        FestParticipant::create([
            'registration_id' => $itemRegistration->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer',
        ]);

        $this->actingAs($admin)->delete(route('school.custom.event.register-students.destroy', [
            'tenantId' => $school->id, 'event' => $event->id, 'student' => $student->id,
        ]));

        // Must stay active — the guard should refuse while an item registration exists.
        $this->assertSame(
            'active',
            FestLevelRegistration::where('student_id', $student->id)->value('status'),
            'withdrawing the event registration must be blocked while the student has an active item registration',
        );

        // Once the item registration is gone, the same action must now succeed.
        $itemRegistration->update(['status' => 'withdrawn']);

        $this->actingAs($admin)->delete(route('school.custom.event.register-students.destroy', [
            'tenantId' => $school->id, 'event' => $event->id, 'student' => $student->id,
        ]));

        $this->assertSame(
            'withdrawn',
            FestLevelRegistration::where('student_id', $student->id)->value('status'),
            'withdrawing must succeed once the blocking item registration is itself withdrawn',
        );
    }
}

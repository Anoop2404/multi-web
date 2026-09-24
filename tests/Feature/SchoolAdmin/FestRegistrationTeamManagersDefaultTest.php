<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestSchoolTeamManager;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A school names an "Events Coordinator" on its own membership application long before any
 * fest event exists. When it registers for an event and hasn't filled in a dedicated Team
 * Manager yet, the registration page should offer that on-file coordinator as the default
 * Team Manager 1 instead of an empty form — see FestRegistrationController::teamManagersProp()
 * and the matching fallback on the Sahodaya-side report (FestReportService::teamManagersData()).
 */
class FestRegistrationTeamManagersDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $applicationPayload = []): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Coordinator Default Sahodaya',
            'domain' => 'coordinator-default-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CD', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Coordinator Default School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
            'application_payload' => $applicationPayload,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Coordinator Default Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        return [$schoolAdmin, $school, $event];
    }

    public function test_team_managers_prop_defaults_to_the_on_file_events_coordinator(): void
    {
        [$schoolAdmin, , $event] = $this->fixture([
            'event_coordinator_name'  => 'Manjusha C',
            'event_coordinator_phone' => '7593991649',
            'event_coordinator_email' => 'manju.c.jayan@gmail.com',
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.event.registration', [
            'tenantId' => $schoolAdmin->tenant_id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $teamManagers = $response->viewData('page')['props']['teamManagers'];

        $this->assertSame('Manjusha C', $teamManagers['manager_name_1']);
        $this->assertSame('7593991649', $teamManagers['manager_phone_1']);
        $this->assertSame('manju.c.jayan@gmail.com', $teamManagers['manager_email_1']);
        $this->assertSame('Events Coordinator (on file)', $teamManagers['manager_role_1']);
    }

    public function test_team_managers_prop_prefers_a_real_saved_manager_over_the_coordinator_default(): void
    {
        [$schoolAdmin, $school, $event] = $this->fixture([
            'event_coordinator_name' => 'Should Not Appear',
        ]);

        FestSchoolTeamManager::create([
            'tenant_id' => $school->parent_id, 'event_id' => $event->id, 'school_id' => $school->id,
            'manager_name_1' => 'Real Manager', 'manager_phone_1' => '1111111111',
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.event.registration', [
            'tenantId' => $schoolAdmin->tenant_id, 'event' => $event->id,
        ]));

        $teamManagers = $response->viewData('page')['props']['teamManagers'];

        $this->assertSame('Real Manager', $teamManagers['manager_name_1']);
        $this->assertNotSame('Should Not Appear', $teamManagers['manager_name_1']);
    }

    public function test_team_managers_prop_is_blank_when_neither_a_manager_nor_a_coordinator_exists(): void
    {
        [$schoolAdmin, , $event] = $this->fixture([]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.event.registration', [
            'tenantId' => $schoolAdmin->tenant_id, 'event' => $event->id,
        ]));

        $teamManagers = $response->viewData('page')['props']['teamManagers'];

        $this->assertNull($teamManagers['manager_name_1']);
        $this->assertNull($teamManagers['manager_role_1']);
    }
}

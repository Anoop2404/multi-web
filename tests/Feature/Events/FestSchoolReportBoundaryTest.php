<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestSchoolReportBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $status = 'draft'): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Report Boundary Sahodaya',
            'domain' => 'report-boundary.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RB',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Report Boundary School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalotsav Boundary Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => $status,
        ]);

        return compact('school', 'admin', 'event');
    }

    public function test_program_route_rejects_an_event_of_another_type(): void
    {
        ['school' => $school, 'admin' => $admin, 'event' => $event] = $this->fixture();

        $this->actingAs($admin)->get(route('school.sports.reports.event', [
            'tenantId' => $school->id,
            'event' => $event->id,
        ]))->assertNotFound();
    }

    public function test_school_export_rejects_a_report_before_its_lifecycle_phase(): void
    {
        ['school' => $school, 'admin' => $admin, 'event' => $event] = $this->fixture('published');

        $this->actingAs($admin)->get(route('school.kalotsav.reports.export', [
            'tenantId' => $school->id,
            'event' => $event->id,
            'exportType' => 'cumulative',
        ]))->assertForbidden();
    }
}

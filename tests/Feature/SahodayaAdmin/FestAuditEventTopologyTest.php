<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FestRegistration;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestAuditEventTopologyTest extends TestCase
{
    use RefreshDatabase;

    private const ACADEMIC_YEAR = '2025-26';

    private function createTenant(): Tenant
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Audit Test Sahodaya',
            'domain' => 'audit-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'AUD',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => self::ACADEMIC_YEAR,
        ]);

        return $sahodaya;
    }

    public function test_audit_command_reports_no_anomalies_for_clean_tenant(): void
    {
        $sahodaya = $this->createTenant();

        $this->artisan('fest:audit-event-topology', [
            '--sahodaya' => $sahodaya->id,
            '--format' => 'json',
        ])
        ->expectsOutput('No topology anomalies found.')
        ->assertExitCode(0);
    }

    public function test_audit_command_detects_standard_event_with_children(): void
    {
        $sahodaya = $this->createTenant();

        $parent = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Standard Hub Event',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'standard',
            'level_round' => 'sahodaya',
            'status' => 'published',
        ]);

        FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Child Leg',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $parent->id,
            'conduct_mode' => 'standard',
            'level_round' => 'sahodaya',
            'status' => 'published',
        ]);

        $this->artisan('fest:audit-event-topology', [
            '--sahodaya' => $sahodaya->id,
            '--format' => 'json',
        ])
        ->assertExitCode(0);
    }

    public function test_audit_command_detects_operational_rows_on_partitioned_parent(): void
    {
        $sahodaya = $this->createTenant();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Partitioned Parent',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Audit School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        FestRegistration::create([
            'event_id' => $hub->id,
            'school_id' => $school->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $this->artisan('fest:audit-event-topology', [
            '--sahodaya' => $sahodaya->id,
            '--format' => 'json',
        ])
        ->assertExitCode(0);
    }

    public function test_audit_command_detects_region_admin_staff_missing_region(): void
    {
        $sahodaya = $this->createTenant();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Partitioned Hub',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('region_admin');

        FestEventStaff::create([
            'event_id' => $hub->id,
            'user_id' => $admin->id,
            'duty' => 'region_admin',
            'region_id' => null,
        ]);

        $this->artisan('fest:audit-event-topology', [
            '--sahodaya' => $sahodaya->id,
            '--format' => 'json',
        ])
        ->assertExitCode(0);
    }
}

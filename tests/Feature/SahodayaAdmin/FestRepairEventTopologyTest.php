<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestRepairEventTopologyTest extends TestCase
{
    use RefreshDatabase;

    private const ACADEMIC_YEAR = '2025-26';

    public function test_repair_command_dry_run_does_not_mutate_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Repair Test Sahodaya',
            'domain' => 'repair-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'REP',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => self::ACADEMIC_YEAR,
        ]);

        $region = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA', 'is_active' => true]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Repair School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        SchoolRegionAssignment::create([
            'tenant_id' => $sahodaya->id,
            'region_id' => $region->id,
            'school_id' => $school->id,
            'academic_year' => self::ACADEMIC_YEAR,
            'source' => 'sahodaya',
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Repair Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $child = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region A Leg',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-a',
            'partition_role' => 'region',
            'region_id' => $region->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $reg = FestRegistration::create([
            'event_id' => $hub->id,
            'school_id' => $school->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $this->artisan('fest:repair-event-topology', [
            '--sahodaya' => $sahodaya->id,
        ])->assertExitCode(0);

        $this->assertEquals($hub->id, $reg->fresh()->event_id);
    }

    public function test_repair_command_commit_relocates_misplaced_registration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Commit Test Sahodaya',
            'domain' => 'commit-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'CMT',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => self::ACADEMIC_YEAR,
        ]);

        $region = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA', 'is_active' => true]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Commit School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        SchoolRegionAssignment::create([
            'tenant_id' => $sahodaya->id,
            'region_id' => $region->id,
            'school_id' => $school->id,
            'academic_year' => self::ACADEMIC_YEAR,
            'source' => 'sahodaya',
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Commit Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $child = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Region A Leg',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-a',
            'partition_role' => 'region',
            'region_id' => $region->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $reg = FestRegistration::create([
            'event_id' => $hub->id,
            'school_id' => $school->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $this->artisan('fest:repair-event-topology', [
            '--sahodaya' => $sahodaya->id,
            '--commit' => true,
        ])->assertExitCode(0);

        $this->assertEquals($child->id, $reg->fresh()->event_id);
    }
}

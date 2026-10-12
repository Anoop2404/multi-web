<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventSchoolPartition;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestSchoolPartitionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestPartitionSchoolSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_sees_parent_event_first_when_no_partition_is_assigned(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Partition Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'PR',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        // Create partitioned hub event
        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Football Tournament',
            'event_type' => 'sports',
            'conduct_mode' => 'partitioned',
            'status' => 'published',
            'nav_hidden' => false,
        ]);

        // Create 2 region partitions
        $part1 = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $hub->id,
            'title' => 'Football Tournament — Manjeri',
            'cluster_label' => 'Manjeri',
            'partition_role' => 'region',
            'partition_key' => 'manjeri',
            'event_type' => 'sports',
            'status' => 'published',
            'nav_hidden' => false,
        ]);

        $part2 = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $hub->id,
            'title' => 'Football Tournament — Nilambur',
            'cluster_label' => 'Nilambur',
            'partition_role' => 'region',
            'partition_key' => 'nilambur',
            'event_type' => 'sports',
            'status' => 'published',
            'nav_hidden' => false,
        ]);

        $allEvents = collect([$hub, $part1, $part2]);

        $filterService = app(FestSchoolPartitionService::class);

        // When unassigned: should keep the hub and reject both child partitions
        $visibleBefore = $filterService->filterVisibleToSchool($allEvents, $school->id);
        $this->assertCount(1, $visibleBefore);
        $this->assertEquals($hub->id, $visibleBefore->first()->id);

        // When assigned to Manjeri: should reject hub and Nilambur, and keep only Manjeri
        FestEventSchoolPartition::create([
            'event_id' => $hub->id,
            'school_id' => $school->id,
            'partition_key' => 'manjeri',
            'assigned_at' => now(),
        ]);

        $visibleAfter = $filterService->filterVisibleToSchool($allEvents, $school->id);
        $this->assertCount(1, $visibleAfter);
        $this->assertEquals($part1->id, $visibleAfter->first()->id);
    }

    public function test_school_can_choose_region_for_partitioned_event_and_is_redirected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Partition Sahodaya 2',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'PR2',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School 2',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $region = Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'Manjeri Region',
            'code' => 'manjeri',
            'is_active' => true,
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Football Tournament',
            'event_type' => 'sports',
            'conduct_mode' => 'partitioned',
            'status' => 'published',
            'nav_hidden' => false,
        ]);

        $part = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $hub->id,
            'title' => 'Football Tournament — Manjeri',
            'cluster_label' => 'Manjeri',
            'partition_role' => 'region',
            'partition_key' => 'manjeri',
            'region_id' => $region->id,
            'event_type' => 'sports',
            'status' => 'published',
            'nav_hidden' => false,
        ]);

        $response = $this->actingAs($schoolAdmin)
            ->post("/school-admin/{$school->id}/sports/events/{$hub->id}/select-partition", [
                'partition_key' => 'manjeri',
            ]);

        $response->assertRedirect("/school-admin/{$school->id}/sports/events/{$part->id}/registration");

        // Verify assignment created in DB
        $this->assertDatabaseHas('fest_event_school_partitions', [
            'event_id' => $hub->id,
            'school_id' => $school->id,
            'partition_key' => 'manjeri',
        ]);

        // Verify membership region assignment created
        $this->assertDatabaseHas('school_region_assignments', [
            'tenant_id' => $sahodaya->id,
            'school_id' => $school->id,
            'region_id' => $region->id,
        ]);
    }
}

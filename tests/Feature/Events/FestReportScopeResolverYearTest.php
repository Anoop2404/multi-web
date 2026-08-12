<?php

namespace Tests\Feature\Events;

use App\Models\AcademicYearRecord;
use App\Models\FestEvent;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\Reports\FestReportScopeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for REG-06 (functional audit, 2026-08-11/12):
 * FestReportScopeResolver::regionScope() previously always resolved "today's"
 * active academic year via AcademicYear::forSahodaya(), regardless of which
 * year the event being reported on actually ran in. SchoolRegionAssignment is
 * year-keyed specifically so a school's region history survives a
 * reassignment — a school assigned to Region A last year and Region B this
 * year should still show under Region A when viewing a report for LAST
 * YEAR's event.
 */
class FestReportScopeResolverYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_scope_uses_the_events_own_year_not_the_current_year(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Year Scope Sahodaya',
            'domain'    => 'year-scope.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'            => $sahodaya->id,
            'prefix'               => 'YS',
            'student_data_mode'    => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $oldYear = AcademicYearRecord::create([
            'label'      => '2024-25',
            'start_date' => '2024-06-01',
            'end_date'   => '2025-05-31',
            'status'     => 'closed',
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RGB', 'is_active' => true]);

        $school = Tenant::create([
            'id'                 => (string) Str::uuid(),
            'type'               => 'school',
            'name'               => 'Reassigned School',
            'parent_id'          => $sahodaya->id,
            'membership_status'  => 'approved',
            'is_active'          => true,
        ]);

        // The school was in Region A last year (2024-25) — the year the event
        // below actually ran in — and has since been moved to Region B for
        // the CURRENT year (2025-26). Both rows coexist by design (one row
        // per school per academic_year).
        SchoolRegionAssignment::create([
            'tenant_id'     => $sahodaya->id,
            'region_id'     => $regionA->id,
            'school_id'     => $school->id,
            'academic_year' => '2024-25',
            'source'        => 'sahodaya',
        ]);
        SchoolRegionAssignment::create([
            'tenant_id'     => $sahodaya->id,
            'region_id'     => $regionB->id,
            'school_id'     => $school->id,
            'academic_year' => '2025-26',
            'source'        => 'sahodaya',
        ]);

        $hub = FestEvent::create([
            'tenant_id'        => $sahodaya->id,
            'title'            => 'Last Year Kalotsav',
            'event_type'       => 'kalolsavam',
            'conduct_mode'     => 'partitioned',
            'level_round'      => 'sahodaya',
            'status'           => 'completed',
            'academic_year_id' => $oldYear->id,
        ]);

        $regionAChild = FestEvent::create([
            'tenant_id'      => $sahodaya->id,
            'title'          => 'Region A Leg (2024-25)',
            'event_type'     => 'kalolsavam',
            'parent_event_id'=> $hub->id,
            'partition_key'  => 'region-a',
            'partition_role' => 'region',
            'region_id'      => $regionA->id,
            'level_round'    => 'sahodaya',
            'status'         => 'completed',
        ]);

        FestEvent::create([
            'tenant_id'      => $sahodaya->id,
            'title'          => 'Region B Leg (2024-25)',
            'event_type'     => 'kalolsavam',
            'parent_event_id'=> $hub->id,
            'partition_key'  => 'region-b',
            'partition_role' => 'region',
            'region_id'      => $regionB->id,
            'level_round'    => 'sahodaya',
            'status'         => 'completed',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $scope = app(FestReportScopeResolver::class)->resolve($hub, $admin, ['mode' => 'region', 'region_id' => $regionA->id]);

        // Correct (post-fix) behavior: the school shows under Region A for
        // this 2024-25 event, because that's the region it was actually in
        // that year — even though it's in Region B today.
        $this->assertContains($school->id, $scope->schoolIds);
        $this->assertSame($regionAChild->id, $scope->eventIds[0] ?? null);

        // And it must NOT show up under Region B for this same past event.
        $regionBScope = app(FestReportScopeResolver::class)->resolve($hub, $admin, ['mode' => 'region', 'region_id' => $regionB->id]);
        $this->assertNotContains($school->id, $regionBScope->schoolIds);
    }
}

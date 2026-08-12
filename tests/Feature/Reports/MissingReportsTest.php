<?php

namespace Tests\Feature\Reports;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\MembershipPayment;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\ErpReportQueryService;
use App\Services\Reports\FestCrossEventReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the 4 reports built for the functional audit's action-plan
 * items 11-14 (2026-08-11/12): approval turnaround time (RPT-DSH-006), my pending
 * approvals (RPT-DSH-007), phase duration/bottleneck (RPT-FST-008), and region
 * performance comparison (RPT-FST-009). None of these existed before.
 */
class MissingReportsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => 'sahodaya-reports',
            'type'      => 'sahodaya',
            'name'      => 'Reports Sahodaya',
            'domain'    => 'reports-test.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'RP',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                 => 'school-reports',
            'type'               => 'school',
            'name'               => 'Reports School',
            'parent_id'          => $sahodaya->id,
            'membership_status'  => 'approved',
            'is_active'          => true,
        ]);

        return compact('sahodaya', 'school');
    }

    public function test_approval_turnaround_time_averages_membership_and_fest_decisions(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        $payment = MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => '2025-26',
            'amount'             => 5000,
            'payment_proof_path' => 'proofs/test.pdf',
            'status'             => 'verified',
            'verified_at'        => now(),
        ]);
        // Direct attribute assignment bypasses mass-assignment protection on
        // created_at/updated_at (not fillable) — the standard way to backdate
        // timestamps in a test.
        $payment->created_at = now()->subHours(2);
        $payment->verified_at = now();
        $payment->save();

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Turnaround Event',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'ongoing',
        ]);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo', 'category' => 'music', 'item_code' => 'SO1']);
        $reg = FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $school->id,
            'status'       => 'approved',
            'submitted_at' => now()->subHours(5),
        ]);
        $reg->updated_at = now();
        $reg->save();

        $rows = app(ErpReportQueryService::class)->rows($sahodaya->id, 'RPT-DSH-006')->keyBy('workflow');

        $this->assertSame(1, $rows['Membership payment verification']['count']);
        $this->assertEqualsWithDelta(2.0, $rows['Membership payment verification']['avg_hours'], 0.2);

        $this->assertSame(1, $rows['Fest registration decision']['count']);
        $this->assertEqualsWithDelta(5.0, $rows['Fest registration decision']['avg_hours'], 0.2);

        // Workflows with no matching rows must still return a row (count 0), not crash
        // or vanish, so the report always lists every workflow it covers.
        $this->assertSame(0, $rows['MCQ registration approval']['count']);
        $this->assertNull($rows['MCQ registration approval']['avg_hours']);
    }

    public function test_my_pending_approvals_is_scoped_by_role(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        MembershipPayment::create([
            'school_id'          => $school->id,
            'academic_year'      => '2025-26',
            'amount'             => 3000,
            'payment_proof_path' => 'proofs/test2.pdf',
            'status'             => 'submitted',
        ]);

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Pending Approvals Event',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'published',
        ]);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Dance', 'category' => 'dance', 'item_code' => 'GD1']);
        FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $school->id,
            'status'       => 'submitted',
            'submitted_at' => now()->subHour(),
        ]);

        $financeUser = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $financeUser->assignRole('sahodaya_finance');

        $staffUser = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $staffUser->assignRole('sahodaya_staff');

        $service = app(ErpReportQueryService::class);

        // No user context (e.g. called outside the ErpReportController wiring) falls
        // back to showing everything — both the membership and the fest rows.
        $allRows = $service->rows($sahodaya->id, 'RPT-DSH-007', []);
        $this->assertCount(2, $allRows);

        // Finance-only sees just the finance queue.
        $financeRows = $service->rows($sahodaya->id, 'RPT-DSH-007', ['_current_user_id' => $financeUser->id]);
        $this->assertCount(1, $financeRows);
        $this->assertSame('Membership payment', $financeRows->first()['workflow']);

        // Staff sees the operational queue but not membership payments (finance-only).
        $staffRows = $service->rows($sahodaya->id, 'RPT-DSH-007', ['_current_user_id' => $staffUser->id]);
        $this->assertCount(1, $staffRows);
        $this->assertSame('Fest registration', $staffRows->first()['workflow']);
    }

    public function test_phase_bottleneck_report_excludes_completed_phases(): void
    {
        ['sahodaya' => $sahodaya] = $this->makeSahodayaAndSchool();

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Phase Mode Event',
            'event_type'         => 'kalolsavam',
            'level_round'        => 'sahodaya',
            'status'             => 'published',
            'phase_mode_enabled' => true,
        ]);

        $stuckPhase = FestEventPhase::create([
            'event_id'   => $event->id,
            'name'       => 'Prelims',
            'code'       => 'PRE',
            'sort_order' => 1,
            'status'     => 'draft',
        ]);
        $stuckPhase->updated_at = now()->subHours(10);
        $stuckPhase->save();

        FestEventPhase::create([
            'event_id'   => $event->id,
            'name'       => 'Finals',
            'code'       => 'FIN',
            'sort_order' => 2,
            'status'     => 'completed',
        ]);

        $rows = app(FestCrossEventReportService::class)->rows($sahodaya->id, 'RPT-FST-008');

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('Prelims', $row['phase']);
        $this->assertGreaterThanOrEqual(9.5, $row['hours_in_current_status']);
    }

    public function test_region_performance_comparison_aggregates_school_points_per_region(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        $hub = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Comparison Kalotsavam',
            'event_type'   => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round'  => 'sahodaya',
            'status'       => 'ongoing',
        ]);

        $regionA = FestEvent::create([
            'tenant_id'       => $sahodaya->id,
            'title'           => 'Region A Leg',
            'event_type'      => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key'   => 'region-a',
            'partition_role'  => 'region',
            'level_round'     => 'sahodaya',
            'status'          => 'ongoing',
        ]);

        $regionB = FestEvent::create([
            'tenant_id'       => $sahodaya->id,
            'title'           => 'Region B Leg',
            'event_type'      => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key'   => 'region-b',
            'partition_role'  => 'region',
            'level_round'     => 'sahodaya',
            'status'          => 'ongoing',
        ]);

        FestResult::create(['event_id' => $regionA->id, 'school_id' => $school->id, 'total_points' => 50]);

        $rows = app(FestCrossEventReportService::class)->rows($sahodaya->id, 'RPT-FST-009')->keyBy('region');

        $this->assertSame(1, $rows['Region A Leg']['schools_participating']);
        $this->assertEqualsWithDelta(50.0, $rows['Region A Leg']['total_points'], 0.01);
        $this->assertEqualsWithDelta(50.0, $rows['Region A Leg']['avg_points_per_school'], 0.01);

        $this->assertSame(0, $rows['Region B Leg']['schools_participating']);
        $this->assertEqualsWithDelta(0.0, $rows['Region B Leg']['total_points'], 0.01);
    }
}

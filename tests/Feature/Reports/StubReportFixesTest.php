<?php

namespace Tests\Feature\Reports;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestFoodBill;
use App\Models\FestFoodOrderItem;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Reports\FestCrossEventReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the "fix/remove stub reports" item of the functional audit's
 * action plan (2026-08-11/12): RPT-KAL-036 was silently misrouted to teamRoster()
 * (meant for suffix 10), RPT-KAL-037/038 (and 39-45) fell through to a generic
 * eventMetrics() fallback, RPT-SPT-037 always returned one fake "not configured" row,
 * and RPT-SPT-038 always returned one hard-coded zero row regardless of real data.
 */
class StubReportFixesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => 'sahodaya-stub',
            'type'      => 'sahodaya',
            'name'      => 'Stub Fix Sahodaya',
            'domain'    => 'stub-fix.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'SF',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id'                 => 'school-stub',
            'type'               => 'school',
            'name'               => 'Stub Fix School',
            'parent_id'          => $sahodaya->id,
            'membership_status'  => 'approved',
            'is_active'          => true,
        ]);

        return compact('sahodaya', 'school');
    }

    public function test_rpt_kal_036_returns_fee_pending_schools_not_team_roster(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Stub Kalotsavam',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'ongoing',
        ]);

        FestSchoolEventFee::create([
            'event_id'  => $event->id,
            'school_id' => $school->id,
            'total_due' => 2500,
            'status'    => 'proof_uploaded',
        ]);

        $rows = app(FestCrossEventReportService::class)->rows($sahodaya->id, 'RPT-KAL-036');

        $this->assertCount(1, $rows);
        $row = $rows->first();
        // teamRoster() rows would have 'participants'/'item' keys; feePendingSchools()
        // rows have 'amount'/'status' — this is the actual proof of the fix.
        $this->assertArrayHasKey('amount', $row);
        $this->assertArrayHasKey('status', $row);
        $this->assertArrayNotHasKey('participants', $row);
    }

    public function test_rpt_kal_037_lists_registrations_awaiting_a_decision(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Approval Queue Event',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'published',
        ]);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo', 'category' => 'music', 'item_code' => 'S1']);

        FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $school->id,
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);
        FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        $rows = app(FestCrossEventReportService::class)->rows($sahodaya->id, 'RPT-KAL-037');

        // Only the 'submitted' registration is pending a decision — 'approved' is not.
        $this->assertCount(1, $rows);
        $this->assertSame('submitted', $rows->first()['status']);
    }

    public function test_rpt_spt_037_gate_entry_log_is_always_empty(): void
    {
        ['sahodaya' => $sahodaya] = $this->makeSahodayaAndSchool();

        FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Sports Meet',
            'event_type'  => 'sports',
            'level_round' => 'sahodaya',
            'status'      => 'ongoing',
        ]);

        $rows = app(FestCrossEventReportService::class)->rows($sahodaya->id, 'RPT-SPT-037');

        // No gate-scanning feature exists — honestly empty, not a fake "not configured" row.
        $this->assertCount(0, $rows);
    }

    public function test_rpt_spt_038_returns_real_food_bill_data(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Sports Meet',
            'event_type'  => 'sports',
            'level_round' => 'sahodaya',
            'status'      => 'ongoing',
        ]);

        $bill = FestFoodBill::create([
            'tenant_id'    => $sahodaya->id,
            'event_id'     => $event->id,
            'school_id'    => $school->id,
            'amount_total' => 1500,
        ]);

        FestFoodOrderItem::create([
            'bill_id'    => $bill->id,
            'menu_date'  => now()->toDateString(),
            'meal_type'  => 'lunch',
            'item_name'  => 'Veg Meal',
            'unit_price' => 150,
            'quantity'   => 10,
            'line_total' => 1500,
        ]);

        $rows = app(FestCrossEventReportService::class)->rows($sahodaya->id, 'RPT-SPT-038');

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(1, $row['orders']);
        $this->assertEqualsWithDelta(1500.0, $row['amount'], 0.01);
    }
}

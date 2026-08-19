<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodCoupon;
use App\Models\FestFoodOrderItem;
use App\Models\FestFoodPayment;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regression tests for Food Module audit 2026-08-17 fixes:
 * - Finding 1: require_payment_for_coupons had no effect on the legacy catering->coupon
 *   flow, letting a school get free coupons on a "payment required" event by using the
 *   older form instead of the priced one.
 * - Finding 2: removing a paid-for order item could drive a bill's balance negative with
 *   no guard, and the bill could still be settled in that state.
 * - Finding 3: settle()/reopen()/cancel() were unlocked and had no (or an incomplete)
 *   status/balance guard — e.g. reopen() could silently un-cancel a "terminal" bill.
 */
class FestFoodBillingSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Food Safety Sahodaya',
            'domain' => 'food-safety-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'FS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Food Safety School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $sahodayaAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'sahodayaAdmin', 'schoolAdmin');
    }

    private function makeEvent(string $sahodayaId, bool $requirePayment = false): FestEvent
    {
        return FestEvent::create([
            'tenant_id' => $sahodayaId,
            'title' => 'Food Safety Event '.Str::random(4),
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'require_payment_for_coupons' => $requirePayment,
        ]);
    }

    private function makeBillWithItem(string $tenantId, string $eventId, string $schoolId, float $price): FestFoodBill
    {
        $bill = FestFoodBill::create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'school_id' => $schoolId,
            'status' => FestFoodBill::STATUS_OPEN,
            'payment_mode' => 'prepaid',
            'payee_type' => 'sahodaya',
            'amount_total' => 0,
            'amount_paid' => 0,
        ]);

        FestFoodOrderItem::create([
            'bill_id' => $bill->id,
            'menu_date' => now()->toDateString(),
            'meal_type' => 'lunch',
            'item_name' => 'Meals',
            'unit_price' => $price,
            'quantity' => 1,
            'line_total' => $price,
        ]);

        $bill->recalculate();

        return $bill->fresh();
    }

    public function test_removing_a_fully_paid_item_is_blocked_instead_of_going_negative(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);

        $bill = $this->makeBillWithItem($sahodaya->id, $event->id, $school->id, 100.0);
        FestFoodPayment::recordForBill($bill, 100.0, 'cash', null, 1);
        $bill->refresh();
        $this->assertSame(0.0, $bill->balanceDue());

        $item = $bill->orderItems()->first();

        try {
            $bill->removeOrderItem($item);
            $this->fail('Expected removeOrderItem() to reject removing a fully-paid item.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // Nothing should have changed — the item is still there and the bill still balanced.
        $bill->refresh();
        $this->assertSame(1, $bill->orderItems()->count());
        $this->assertSame(0.0, $bill->balanceDue());
    }

    public function test_removing_an_unpaid_item_still_works_when_balance_stays_covered(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);

        $bill = $this->makeBillWithItem($sahodaya->id, $event->id, $school->id, 50.0);
        FestFoodOrderItem::create([
            'bill_id' => $bill->id,
            'menu_date' => now()->toDateString(),
            'meal_type' => 'dinner',
            'item_name' => 'Snacks',
            'unit_price' => 20.0,
            'quantity' => 1,
            'line_total' => 20.0,
        ]);
        $bill->recalculate();
        $bill->refresh();
        $this->assertEquals(70.0, (float) $bill->amount_total);

        FestFoodPayment::recordForBill($bill, 50.0, 'cash', null, 1);
        $bill->refresh();

        $unpaidItem = $bill->orderItems()->where('item_name', 'Snacks')->first();
        $bill->removeOrderItem($unpaidItem);

        $bill->refresh();
        $this->assertEquals(50.0, (float) $bill->amount_total);
        $this->assertSame(0.0, $bill->balanceDue());
    }

    public function test_settle_rejects_an_outstanding_balance_even_under_a_fresh_lock(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);
        $bill = $this->makeBillWithItem($sahodaya->id, $event->id, $school->id, 100.0);

        $this->expectException(HttpException::class);
        $bill->settle(1);
    }

    public function test_settle_succeeds_once_fully_paid(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);
        $bill = $this->makeBillWithItem($sahodaya->id, $event->id, $school->id, 100.0);
        FestFoodPayment::recordForBill($bill, 100.0, 'cash', null, 1);
        $bill->refresh();

        $bill->settle(1);

        $bill->refresh();
        $this->assertSame(FestFoodBill::STATUS_SETTLED, $bill->status);
    }

    public function test_reopen_rejects_a_cancelled_bill(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);
        $bill = $this->makeBillWithItem($sahodaya->id, $event->id, $school->id, 100.0);

        $bill->cancel();
        $bill->refresh();
        $this->assertSame(FestFoodBill::STATUS_CANCELLED, $bill->status);

        try {
            $bill->reopen();
            $this->fail('Expected reopen() to reject a cancelled (terminal) bill.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $bill->refresh();
        $this->assertSame(FestFoodBill::STATUS_CANCELLED, $bill->status);
    }

    public function test_cancel_rejects_when_payments_exist(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);
        $bill = $this->makeBillWithItem($sahodaya->id, $event->id, $school->id, 100.0);
        FestFoodPayment::recordForBill($bill, 40.0, 'cash', null, 1);
        $bill->refresh();

        $this->expectException(HttpException::class);
        $bill->cancel();
    }

    public function test_legacy_catering_coupon_issuance_is_blocked_when_event_requires_payment(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'sahodayaAdmin' => $admin] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id, requirePayment: true);

        FestCateringOrder::create([
            'event_id' => $event->id,
            'school_id' => $school->id,
            'meal_date' => now()->toDateString(),
            'meal_type' => 'lunch',
            'head_count' => 200,
            'status' => 'confirmed',
            'submitted_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.food-coupons.issue', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]));

        $response->assertStatus(422);
        $this->assertSame(0, FestFoodCoupon::where('event_id', $event->id)->count());
    }

    public function test_legacy_catering_coupon_issuance_still_works_when_event_does_not_require_payment(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'sahodayaAdmin' => $admin] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id, requirePayment: false);

        FestCateringOrder::create([
            'event_id' => $event->id,
            'school_id' => $school->id,
            'meal_date' => now()->toDateString(),
            'meal_type' => 'lunch',
            'head_count' => 200,
            'status' => 'confirmed',
            'submitted_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.food-coupons.issue', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]));

        $response->assertRedirect();
        $this->assertSame(1, FestFoodCoupon::where('event_id', $event->id)->count());
    }

    public function test_catering_order_submission_is_blocked_when_event_requires_payment(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'schoolAdmin' => $schoolAdmin] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id, requirePayment: true);

        $response = $this->actingAs($schoolAdmin)->post(route('school.fest.catering.store', [
            'tenantId' => $school->id,
            'event' => $event->id,
        ]), [
            'meal_date' => now()->toDateString(),
            'meal_type' => 'lunch',
            'head_count' => 50,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FestCateringOrder::where('event_id', $event->id)->count());
    }

    /**
     * Regression test: opening a bill for a school validated `school_id` against the
     * `tenants` table with no explicit connection. Since this runs inside tenant-DB
     * context (the default connection is the tenant's own DB, where `tenants` doesn't
     * exist), the exists check threw a QueryException instead of validating — this
     * action was completely broken for every event, in every conduct mode.
     */
    public function test_opening_a_bill_for_a_school_succeeds(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'sahodayaAdmin' => $admin] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.food-billing.store', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'school_id' => $school->id,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, FestFoodBill::where('event_id', $event->id)->where('school_id', $school->id)->count());
    }

    /** Regression test: same `tenants`-table validation bug as store(), on the payee update. */
    public function test_setting_a_host_school_as_food_payee_succeeds(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'sahodayaAdmin' => $admin] = $this->makeSahodayaAndSchool();
        $event = $this->makeEvent($sahodaya->id);

        $response = $this->actingAs($admin)->put(route('sahodaya.events.food-menu.payee.update', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'food_payee_type' => 'host_school',
            'food_host_school_id' => $school->id,
        ]);

        $response->assertRedirect();
        $this->assertSame($school->id, $event->fresh()->food_host_school_id);
    }
}

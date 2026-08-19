<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodOrderItem;
use App\Models\FestFoodPayment;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

/**
 * Coverage for App\Http\Controllers\SchoolAdmin\FestFoodHostBillingController — food
 * billing management for the one school designated as an event's food payee
 * (food_payee_type === 'host_school'). Previously untested (tests/Feature/SchoolAdmin/
 * had zero food-controller coverage at all).
 *
 * Mirrors the money-correctness assertions already proven at the model level for the
 * Sahodaya-side controller in FestFoodBillingSafetyTest — removeOrderItem()'s
 * negative-balance guard, and settle()/reopen()'s lock-and-recheck guards — since
 * FestFoodBill centralizes those on the model itself and both controllers call the same
 * methods. Does NOT assume the two controllers are wired identically otherwise: verified
 * directly against source that this controller has no cancel() action at all (see the
 * dedicated test below), unlike SahodayaAdmin\FestFoodBillingController.
 */
class FestFoodHostBillingControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, hostSchool: Tenant, hostAdmin: User} */
    private function makeSahodayaAndHostSchool(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Host Billing Sahodaya',
            'domain' => 'host-billing-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'HB',
            'student_data_mode' => 'counts_only',
        ]);

        $hostSchool = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Host School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $hostAdmin = User::factory()->create(['tenant_id' => $hostSchool->id, 'email_verified_at' => now()]);
        $hostAdmin->assignRole('school_admin');

        return compact('sahodaya', 'hostSchool', 'hostAdmin');
    }

    private function makeEventHostedBy(Tenant $sahodaya, Tenant $hostSchool, array $overrides = []): FestEvent
    {
        return FestEvent::create(array_merge([
            'tenant_id' => $sahodaya->id,
            'title' => 'Hosted Fest '.Str::random(4),
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'food_payee_type' => 'host_school',
            'food_host_school_id' => $hostSchool->id,
        ], $overrides));
    }

    private function makeBillWithItem(string $tenantId, FestEvent $event, ?string $hostSchoolId, string $orderingSchoolId, float $price): FestFoodBill
    {
        $bill = FestFoodBill::create([
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'school_id' => $orderingSchoolId,
            'status' => FestFoodBill::STATUS_OPEN,
            'payment_mode' => 'prepaid',
            'payee_type' => $hostSchoolId ? 'host_school' : 'sahodaya',
            'host_school_id' => $hostSchoolId,
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

    public function test_host_school_can_view_and_add_items_to_its_own_bill(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();
        $event = $this->makeEventHostedBy($sahodaya, $hostSchool);
        $bill = $this->makeBillWithItem($sahodaya->id, $event, $hostSchool->id, (string) Str::uuid(), 60.0);
        $menuItem = FestFoodMenuItem::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'menu_date' => now()->toDateString(),
            'meal_type' => 'dinner', 'name' => 'Snacks', 'price' => 20, 'is_available' => true, 'sort_order' => 0,
        ]);

        $this->actingAs($hostAdmin)
            ->get(route('school.food-host-billing.index', ['tenantId' => $hostSchool->id, 'event' => $event->id]))
            ->assertOk();

        $this->actingAs($hostAdmin)
            ->get(route('school.food-host-billing.show', ['tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id]))
            ->assertOk();

        $add = $this->actingAs($hostAdmin)->post(route('school.food-host-billing.items.store', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 2]);
        $add->assertRedirect();

        $this->assertSame(100.0, (float) $bill->fresh()->amount_total);
    }

    public function test_host_school_cannot_access_billing_for_an_event_it_is_not_the_designated_host_for(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();

        // Event B is hosted by a completely different (unrelated) school.
        $otherHostSchoolId = (string) Str::uuid();
        $eventB = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Other Hosted Fest', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'ongoing',
            'food_payee_type' => 'host_school', 'food_host_school_id' => $otherHostSchoolId,
        ]);
        $billOnB = $this->makeBillWithItem($sahodaya->id, $eventB, $otherHostSchoolId, (string) Str::uuid(), 50.0);

        $response = $this->actingAs($hostAdmin)->get(route('school.food-host-billing.show', [
            'tenantId' => $hostSchool->id, 'event' => $eventB->id, 'bill' => $billOnB->id,
        ]));

        $response->assertStatus(403);
    }

    /**
     * assertBillBelongsToHost() must key off the BILL's own payee snapshot, not the
     * event's current (mutable) food_host_school_id — the "harder to get right" check the
     * Food Module audit praised. $hostSchool IS the event's current host here, but this
     * particular bill was snapshotted to 'sahodaya' as payee, so it must stay off limits.
     */
    public function test_bill_not_snapshotted_to_this_school_is_blocked_even_though_it_is_the_events_current_host(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();
        $event = $this->makeEventHostedBy($sahodaya, $hostSchool);
        $sahodayaPayeeBill = $this->makeBillWithItem($sahodaya->id, $event, null, (string) Str::uuid(), 50.0);
        $this->assertSame('sahodaya', $sahodayaPayeeBill->payee_type);

        $response = $this->actingAs($hostAdmin)->get(route('school.food-host-billing.show', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $sahodayaPayeeBill->id,
        ]));

        $response->assertStatus(403);
    }

    public function test_record_payment_rejects_an_amount_exceeding_the_outstanding_balance(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();
        $event = $this->makeEventHostedBy($sahodaya, $hostSchool);
        $bill = $this->makeBillWithItem($sahodaya->id, $event, $hostSchool->id, (string) Str::uuid(), 100.0);

        $response = $this->actingAs($hostAdmin)->post(route('school.food-host-billing.payments.store', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id,
        ]), ['amount' => 150, 'payment_mode' => 'cash']);

        $response->assertStatus(422);
        $this->assertSame(0.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_remove_item_is_blocked_when_it_would_drive_the_balance_negative(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();
        $event = $this->makeEventHostedBy($sahodaya, $hostSchool);
        $bill = $this->makeBillWithItem($sahodaya->id, $event, $hostSchool->id, (string) Str::uuid(), 100.0);
        FestFoodPayment::recordForBill($bill, 100.0, 'cash', null, 1);
        $bill->refresh();
        $orderItem = $bill->orderItems()->firstOrFail();

        $response = $this->actingAs($hostAdmin)->delete(route('school.food-host-billing.items.destroy', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id, 'orderItem' => $orderItem->id,
        ]));

        $response->assertStatus(422);
        $this->assertSame(1, $bill->orderItems()->count());
        $this->assertSame(0.0, $bill->fresh()->balanceDue());
    }

    public function test_settle_is_blocked_with_an_outstanding_balance_and_succeeds_once_fully_paid(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();
        $event = $this->makeEventHostedBy($sahodaya, $hostSchool);
        $bill = $this->makeBillWithItem($sahodaya->id, $event, $hostSchool->id, (string) Str::uuid(), 100.0);

        $blocked = $this->actingAs($hostAdmin)->post(route('school.food-host-billing.settle', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id,
        ]));
        $blocked->assertStatus(422);
        $this->assertSame(FestFoodBill::STATUS_OPEN, $bill->fresh()->status);

        FestFoodPayment::recordForBill($bill, 100.0, 'cash', null, 1);

        $ok = $this->actingAs($hostAdmin)->post(route('school.food-host-billing.settle', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id,
        ]));
        $ok->assertRedirect();
        $this->assertSame(FestFoodBill::STATUS_SETTLED, $bill->fresh()->status);
    }

    public function test_reopen_rejects_a_cancelled_bill_and_an_already_open_bill_via_the_host_billing_route(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();
        $event = $this->makeEventHostedBy($sahodaya, $hostSchool);
        $bill = $this->makeBillWithItem($sahodaya->id, $event, $hostSchool->id, (string) Str::uuid(), 100.0);

        $alreadyOpen = $this->actingAs($hostAdmin)->post(route('school.food-host-billing.reopen', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id,
        ]));
        $alreadyOpen->assertStatus(422);

        // This controller exposes no cancel() action of its own (see the dedicated test
        // below), so the cancelled state is set directly on the model here, exactly like
        // FestFoodBillingSafetyTest::test_reopen_rejects_a_cancelled_bill() does for the
        // Sahodaya-side controller — this test is only exercising that the host-billing
        // ROUTE correctly surfaces FestFoodBill::reopen()'s guard.
        $bill->cancel();

        $cancelled = $this->actingAs($hostAdmin)->post(route('school.food-host-billing.reopen', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id,
        ]));
        $cancelled->assertStatus(422);
        $this->assertSame(FestFoodBill::STATUS_CANCELLED, $bill->fresh()->status);
    }

    public function test_void_payment_removes_the_payment_and_recalculates_the_bill(): void
    {
        ['sahodaya' => $sahodaya, 'hostSchool' => $hostSchool, 'hostAdmin' => $hostAdmin] = $this->makeSahodayaAndHostSchool();
        $event = $this->makeEventHostedBy($sahodaya, $hostSchool);
        $bill = $this->makeBillWithItem($sahodaya->id, $event, $hostSchool->id, (string) Str::uuid(), 100.0);
        $payment = FestFoodPayment::recordForBill($bill, 40.0, 'cash', null, 1);
        $bill->refresh();
        $this->assertSame(60.0, $bill->balanceDue());

        $response = $this->actingAs($hostAdmin)->delete(route('school.food-host-billing.payments.void', [
            'tenantId' => $hostSchool->id, 'event' => $event->id, 'bill' => $bill->id, 'payment' => $payment->id,
        ]));

        $response->assertRedirect();
        $this->assertSame(0, FestFoodPayment::where('id', $payment->id)->count());
        $this->assertSame(0.0, (float) $bill->fresh()->amount_paid);
    }

    /**
     * Real, verified gap: SahodayaAdmin\FestFoodBillingController has a cancel() action
     * (routes/web.php: '.../food-billing/{bill}/cancel' -> FestFoodBillingController::
     * cancel()), but SchoolAdmin\FestFoodHostBillingController has no cancel() method at
     * all, and routes/web.php registers no `school.food-host-billing.cancel` route (see
     * the full food-host-billing.* route block, routes/web.php:485-496) — confirmed by
     * reading both files directly, not assumed from the audit doc. A host school that
     * opens a bill in error has no way to cancel it themselves; only a Sahodaya admin can,
     * from the Sahodaya-side page.
     */
    public function test_the_host_billing_controller_exposes_no_cancel_action_unlike_the_sahodaya_side(): void
    {
        $this->expectException(RouteNotFoundException::class);

        route('school.food-host-billing.cancel', ['tenantId' => 'x', 'event' => 1, 'bill' => 1]);
    }
}

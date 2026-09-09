<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodPayment;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A school-submitted food payment (proof upload + transaction ref) sits as
 * FestFoodPayment::STATUS_PENDING and must NOT count toward FestFoodBill::amount_paid
 * until a staff member on the receiving side (Sahodaya, or the designated host school)
 * explicitly approves it — unlike a staff-entered payment (recordPayment()), which is
 * still immediate/auto-approved, matching pre-existing behavior exactly.
 */
class FestFoodPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private Tenant $school;

    private User $schoolAdmin;

    private User $sahodayaAdmin;

    private FestEvent $event;

    private FestFoodMenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Food Payment Review Sahodaya',
            'domain' => 'food-review-'.Str::random(8).'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $this->sahodaya->id, 'prefix' => 'FR', 'student_data_mode' => 'counts_only']);

        $this->school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Food Payment Review School',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $this->schoolAdmin = User::factory()->create(['tenant_id' => $this->school->id, 'email_verified_at' => now()]);
        $this->schoolAdmin->assignRole('school_admin');

        $this->sahodayaAdmin = User::factory()->create(['tenant_id' => $this->sahodaya->id, 'email_verified_at' => now()]);
        $this->sahodayaAdmin->assignRole('sahodaya_admin');

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Food Payment Review Fest',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);

        $this->menuItem = FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'menu_date' => '2026-09-01',
            'meal_type' => 'lunch',
            'name' => 'Meals',
            'price' => 100,
            'is_available' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), ['menu_item_id' => $this->menuItem->id, 'quantity' => 1]);
    }

    private function bill(): FestFoodBill
    {
        return FestFoodBill::where('event_id', $this->event->id)->where('school_id', $this->school->id)->firstOrFail();
    }

    public function test_school_submitted_payment_is_pending_and_does_not_count_toward_amount_paid(): void
    {
        $response = $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), [
            'amount' => 100,
            'payment_mode' => 'upi',
            'transaction_ref' => 'UTR123456',
            'bank_name' => 'Test Bank',
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $bill = $this->bill();
        $this->assertSame(0.0, (float) $bill->amount_paid);

        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();
        $this->assertSame(FestFoodPayment::STATUS_PENDING, $payment->status);
        $this->assertSame($this->schoolAdmin->id, $payment->submitted_by_user_id);
        $this->assertNotNull($payment->submitted_at);
        $this->assertNotNull($payment->proof_path);
        $this->assertSame('UTR123456', $payment->transaction_ref);
    }

    public function test_sahodaya_admin_approving_a_pending_payment_updates_the_bill_balance(): void
    {
        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), [
            'amount' => 100, 'payment_mode' => 'upi', 'proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $bill = $this->bill();
        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();

        $response = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.payments.approve', [
            'tenantId' => $this->sahodaya->id, 'event' => $this->event->id, 'bill' => $bill->id, 'payment' => $payment->id,
        ]));

        $response->assertRedirect();
        $payment->refresh();
        $this->assertSame(FestFoodPayment::STATUS_APPROVED, $payment->status);
        $this->assertSame($this->sahodayaAdmin->id, $payment->reviewed_by_user_id);
        $this->assertNotNull($payment->receipt_number);
        $this->assertSame(100.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_sahodaya_admin_rejecting_a_pending_payment_leaves_the_balance_unpaid(): void
    {
        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), [
            'amount' => 100, 'payment_mode' => 'upi', 'proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $bill = $this->bill();
        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();

        $response = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.payments.reject', [
            'tenantId' => $this->sahodaya->id, 'event' => $this->event->id, 'bill' => $bill->id, 'payment' => $payment->id,
        ]), ['reason' => 'UTR does not match our bank statement']);

        $response->assertRedirect();
        $payment->refresh();
        $this->assertSame(FestFoodPayment::STATUS_REJECTED, $payment->status);
        $this->assertSame('UTR does not match our bank statement', $payment->rejection_reason);
        $this->assertSame(0.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_a_pending_payment_cannot_be_approved_twice(): void
    {
        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), [
            'amount' => 100, 'payment_mode' => 'upi', 'proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $bill = $this->bill();
        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();

        $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.payments.approve', [
            'tenantId' => $this->sahodaya->id, 'event' => $this->event->id, 'bill' => $bill->id, 'payment' => $payment->id,
        ]));

        $second = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.payments.approve', [
            'tenantId' => $this->sahodaya->id, 'event' => $this->event->id, 'bill' => $bill->id, 'payment' => $payment->id,
        ]));

        $second->assertStatus(422);
        $this->assertSame(100.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_staff_entered_payment_is_still_immediately_approved_and_counts_toward_balance(): void
    {
        $bill = $this->bill();

        $response = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.payments.store', [
            'tenantId' => $this->sahodaya->id, 'event' => $this->event->id, 'bill' => $bill->id,
        ]), ['amount' => 100, 'payment_mode' => 'cash']);

        $response->assertRedirect();
        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();
        $this->assertSame(FestFoodPayment::STATUS_APPROVED, $payment->status);
        $this->assertSame($this->sahodayaAdmin->id, $payment->reviewed_by_user_id);
        $this->assertSame(100.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_host_school_admin_can_approve_a_payment_on_a_bill_payable_to_their_school(): void
    {
        $hostSchool = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Host School',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $hostAdmin = User::factory()->create(['tenant_id' => $hostSchool->id, 'email_verified_at' => now()]);
        $hostAdmin->assignRole('school_admin');

        $this->event->update(['food_payee_type' => 'host_school', 'food_host_school_id' => $hostSchool->id]);
        // The bill was created (in setUp()) before the payee change above — real Sahodaya
        // admins set this before schools start ordering, but recreate here so this test's
        // bill is snapshotted to the host, matching FestFoodBill::firstOrCreateForSchool()'s
        // payee-snapshot-at-creation-time behavior.
        $this->bill()->delete();
        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), ['menu_item_id' => $this->menuItem->id, 'quantity' => 1]);

        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), [
            'amount' => 100, 'payment_mode' => 'upi', 'proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $bill = $this->bill();
        $this->assertSame('host_school', $bill->payee_type);
        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();

        $response = $this->actingAs($hostAdmin)->post(route('school.food-host-billing.payments.approve', [
            'tenantId' => $hostSchool->id, 'event' => $this->event->id, 'bill' => $bill->id, 'payment' => $payment->id,
        ]));

        $response->assertRedirect();
        $this->assertSame(FestFoodPayment::STATUS_APPROVED, $payment->fresh()->status);
        $this->assertSame(100.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_a_different_school_cannot_approve_its_own_payments(): void
    {
        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id, 'event' => $this->event->id,
        ]), [
            'amount' => 100, 'payment_mode' => 'upi', 'proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        // No school-side approve route exists at all for the ordering school itself — only
        // Sahodaya-admin and (when applicable) the designated host school can review.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('school.food-order.payments.approve'));
    }
}

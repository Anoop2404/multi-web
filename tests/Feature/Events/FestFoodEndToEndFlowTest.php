<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodCatalogItem;
use App\Models\FestFoodMenuItem;
use App\Models\FestFoodOrderItem;
use App\Models\FestFoodPayment;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FestFoodEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;
    private Tenant $school;
    private Tenant $hostSchool;
    private User $sahodayaAdmin;
    private User $schoolAdmin;
    private User $hostAdmin;
    private FestEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Demo Sahodaya',
            'domain' => 'demo-sahodaya.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $this->sahodaya->id,
            'prefix' => 'DS',
            'student_data_mode' => 'counts_only',
        ]);

        $this->school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Participating School',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $this->hostSchool = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Host School',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $this->sahodayaAdmin = User::factory()->create([
            'tenant_id' => $this->sahodaya->id,
            'email' => 'admin@demo-sahodaya.test',
            'email_verified_at' => now(),
        ]);
        $this->sahodayaAdmin->assignRole('sahodaya_admin');

        $this->schoolAdmin = User::factory()->create([
            'tenant_id' => $this->school->id,
            'email' => 'admin@school.test',
            'email_verified_at' => now(),
        ]);
        $this->schoolAdmin->assignRole('school_admin');

        $this->hostAdmin = User::factory()->create([
            'tenant_id' => $this->hostSchool->id,
            'email' => 'admin@host-school.test',
            'email_verified_at' => now(),
        ]);
        $this->hostAdmin->assignRole('school_admin');

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Annual Cultural Fest 2026',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
            'food_payee_type' => 'sahodaya',
        ]);
    }

    public function test_sahodaya_admin_login_and_food_menu_lifecycle(): void
    {
        // 1. Sahodaya Admin visits Food Menu
        $response = $this->actingAs($this->sahodayaAdmin)->get(route('sahodaya.events.food-menu.index', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sahodaya/Events/FoodMenu', false)
            ->has('event')
            ->where('event.event_type', 'kalolsavam')
            ->has('catalogItems')
            ->has('menuItems')
            ->has('eventDates')
        );

        // 2. Sahodaya Admin creates item in Master Catalog
        $catResponse = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-catalog.store', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]), [
            'name' => 'Paneer Butter Masala & Rotis',
            'default_price' => 120,
            'description' => 'Rich vegetarian curry with hot wheat rotis',
            'is_active' => true,
        ]);
        $catResponse->assertRedirect();
        $catalogItem = FestFoodCatalogItem::where('event_id', $this->event->id)->firstOrFail();
        $this->assertSame('Paneer Butter Masala & Rotis', $catalogItem->name);
        $this->assertSame(120.0, (float) $catalogItem->default_price);

        // 3. Sahodaya Admin assigns dish to a meal slot
        $assignResponse = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-menu.assign-catalog-items', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]), [
            'menu_date' => '2026-10-15',
            'meal_type' => 'lunch',
            'catalog_item_ids' => [$catalogItem->id],
        ]);
        $assignResponse->assertRedirect();

        $menuItem = FestFoodMenuItem::where('event_id', $this->event->id)
            ->where('menu_date', '2026-10-15')
            ->where('meal_type', 'lunch')
            ->firstOrFail();
        $this->assertSame('Paneer Butter Masala & Rotis', $menuItem->name);
        $this->assertSame(120.0, (float) $menuItem->price);
    }

    public function test_school_admin_login_food_order_and_payment_proof_submission(): void
    {
        // Setup menu item
        $menuItem = FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'menu_date' => '2026-10-15',
            'meal_type' => 'lunch',
            'name' => 'South Indian Meals',
            'price' => 80,
            'is_available' => true,
            'sort_order' => 0,
        ]);

        // 1. School Admin accesses Food Order page
        $response = $this->actingAs($this->schoolAdmin)->get(route('school.food-order.show', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('School/Fest/FoodOrder', false)
            ->has('event')
            ->has('festEvent')
            ->where('festEvent.event_type', 'kalolsavam')
            ->has('menuItems')
        );

        // 2. School Admin places an order
        $orderResponse = $this->actingAs($this->schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), [
            'menu_item_id' => $menuItem->id,
            'quantity' => 10,
        ]);
        $orderResponse->assertRedirect();
        $orderResponse->assertSessionHas('success');

        $bill = FestFoodBill::where('event_id', $this->event->id)->where('school_id', $this->school->id)->firstOrFail();
        $this->assertSame(800.0, (float) $bill->amount_total);
        $this->assertSame(0.0, (float) $bill->amount_paid);

        // 3. School Admin submits payment proof
        $file = UploadedFile::fake()->image('transfer_receipt.png', 600, 400);
        $payResponse = $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), [
            'amount' => 800,
            'payment_mode' => 'upi',
            'transaction_ref' => 'UPI20261015999',
            'proof' => $file,
        ]);
        $payResponse->assertRedirect();
        $payResponse->assertSessionHas('success');

        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();
        $this->assertSame(FestFoodPayment::STATUS_PENDING, $payment->status);
        $this->assertSame('UPI20261015999', $payment->transaction_ref);
        $this->assertNotNull($payment->proof_path);

        // 4. School Admin can view the uploaded proof file
        $proofResponse = $this->actingAs($this->schoolAdmin)->get(route('school.food-order.payments.proof', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
            'payment' => $payment->id,
        ]));
        $proofResponse->assertOk();
    }

    public function test_admin_food_billing_inspection_and_payment_approval_flow(): void
    {
        // Prepare bill with item and pending payment proof
        $menuItem = FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'menu_date' => '2026-10-15',
            'meal_type' => 'dinner',
            'name' => 'Veg Biryani',
            'price' => 100,
            'is_available' => true,
        ]);

        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), [
            'menu_item_id' => $menuItem->id,
            'quantity' => 5,
        ]);

        $file = UploadedFile::fake()->image('upi_screenshot.png');
        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), [
            'amount' => 500,
            'payment_mode' => 'upi',
            'transaction_ref' => 'UPIREF8888',
            'proof' => $file,
        ]);

        $bill = FestFoodBill::where('event_id', $this->event->id)->where('school_id', $this->school->id)->firstOrFail();
        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();

        // 1. Sahodaya Admin visits Food Billing Index
        $billingListResp = $this->actingAs($this->sahodayaAdmin)->get(route('sahodaya.events.food-billing.index', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]));
        $billingListResp->assertOk();
        $billingListResp->assertInertia(fn (Assert $page) => $page
            ->component('Sahodaya/Events/FoodBilling', false)
            ->has('bills')
            ->has('summary')
            ->has('event')
            ->where('event.event_type', 'kalolsavam')
        );

        // 2. Sahodaya Admin views Bill Detail
        $billDetailResp = $this->actingAs($this->sahodayaAdmin)->get(route('sahodaya.events.food-billing.show', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
            'bill' => $bill->id,
        ]));
        $billDetailResp->assertOk();
        $billDetailResp->assertInertia(fn (Assert $page) => $page
            ->component('Sahodaya/Events/FoodBillingShow', false)
            ->has('bill')
            ->has('event')
            ->where('event.event_type', 'kalolsavam')
            ->where('bill.amount_total', fn ($v) => (float) $v === 500.0)
            ->where('bill.amount_paid', fn ($v) => (float) $v === 0.0)
            ->has('payments', 1)
        );

        // 3. Sahodaya Admin views proof document
        $adminProofResp = $this->actingAs($this->sahodayaAdmin)->get(route('sahodaya.events.food-billing.payments.proof', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
            'bill' => $bill->id,
            'payment' => $payment->id,
        ]));
        $adminProofResp->assertOk();

        // 4. Sahodaya Admin approves the pending payment proof
        $approveResp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.payments.approve', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
            'bill' => $bill->id,
            'payment' => $payment->id,
        ]));
        $approveResp->assertRedirect();

        $payment->refresh();
        $bill->refresh();
        $this->assertSame(FestFoodPayment::STATUS_APPROVED, $payment->status);
        $this->assertSame(500.0, (float) $bill->amount_paid);

        // 5. Sahodaya Admin settles the fully paid bill
        $settleResp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.settle', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
            'bill' => $bill->id,
        ]));
        $settleResp->assertRedirect();
        $this->assertSame(FestFoodBill::STATUS_SETTLED, $bill->fresh()->status);
    }

    public function test_host_school_admin_food_host_billing_flow(): void
    {
        // Configure event payee to host school
        $this->event->update([
            'food_payee_type' => 'host_school',
            'food_host_school_id' => $this->hostSchool->id,
        ]);

        $menuItem = FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'menu_date' => '2026-10-16',
            'meal_type' => 'breakfast',
            'name' => 'Idli Vada Set',
            'price' => 60,
            'is_available' => true,
        ]);

        // Participating school orders food
        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), [
            'menu_item_id' => $menuItem->id,
            'quantity' => 4,
        ]);

        $bill = FestFoodBill::where('event_id', $this->event->id)->where('school_id', $this->school->id)->firstOrFail();
        $this->assertSame('host_school', $bill->payee_type);
        $this->assertSame(240.0, (float) $bill->amount_total);

        // Host School Admin visits Host Billing Index
        $hostIndexResp = $this->actingAs($this->hostAdmin)->get(route('school.food-host-billing.index', [
            'tenantId' => $this->hostSchool->id,
            'event' => $this->event->id,
        ]));
        $hostIndexResp->assertOk();
        $hostIndexResp->assertInertia(fn (Assert $page) => $page
            ->component('School/Fest/FoodHostBilling', false)
            ->has('bills')
            ->has('summary')
            ->has('event')
            ->where('event.event_type', 'kalolsavam')
        );

        // Host School Admin visits Host Bill Detail
        $hostDetailResp = $this->actingAs($this->hostAdmin)->get(route('school.food-host-billing.show', [
            'tenantId' => $this->hostSchool->id,
            'event' => $this->event->id,
            'bill' => $bill->id,
        ]));
        $hostDetailResp->assertOk();
        $hostDetailResp->assertInertia(fn (Assert $page) => $page
            ->component('School/Fest/FoodHostBillingShow', false)
            ->has('bill')
            ->has('event')
            ->where('event.event_type', 'kalolsavam')
        );

        // Host School records a direct payment
        $hostPayResp = $this->actingAs($this->hostAdmin)->post(route('school.food-host-billing.payments.store', [
            'tenantId' => $this->hostSchool->id,
            'event' => $this->event->id,
            'bill' => $bill->id,
        ]), [
            'amount' => 240,
            'payment_mode' => 'cash',
            'notes' => 'Received cash at counter',
        ]);
        $hostPayResp->assertRedirect();
        $this->assertSame(240.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_sahodaya_admin_can_reject_payment_proof_with_reason(): void
    {
        $menuItem = FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'menu_date' => '2026-10-15',
            'meal_type' => 'lunch',
            'name' => 'Meals',
            'price' => 50,
            'is_available' => true,
        ]);

        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 2]);

        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.payments.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), [
            'amount' => 100,
            'payment_mode' => 'bank_transfer',
            'transaction_ref' => 'REF_INVALID_UTR',
            'proof' => UploadedFile::fake()->image('invalid_screenshot.jpg'),
        ]);

        $bill = FestFoodBill::where('event_id', $this->event->id)->where('school_id', $this->school->id)->firstOrFail();
        $payment = FestFoodPayment::where('bill_id', $bill->id)->firstOrFail();

        $rejectResp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-billing.payments.reject', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
            'bill' => $bill->id,
            'payment' => $payment->id,
        ]), [
            'reason' => 'Invalid transaction reference code',
        ]);

        $rejectResp->assertRedirect();
        $payment->refresh();
        $this->assertSame(FestFoodPayment::STATUS_REJECTED, $payment->status);
        $this->assertSame('Invalid transaction reference code', $payment->rejection_reason);
        $this->assertSame(0.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_school_admin_event_navigation_context_is_fully_preserved_in_food_order(): void
    {
        // When School Admin accesses the food order page, auto-injected navigation props must be present
        $response = $this->actingAs($this->schoolAdmin)->get(route('school.food-order.show', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('School/Fest/FoodOrder', false)
            ->where('event.event_type', 'kalolsavam')
            ->where('program', 'kalotsav')
            ->where('programPrefix', 'kalotsav')
            ->has('eventHeadNav')
            ->has('festEvent')
            ->where('festEvent.id', $this->event->id)
            ->where('festEvent.event_type', 'kalolsavam')
        );
    }

    public function test_food_coupons_issuance_from_bill_and_redemption(): void
    {
        $menuItem = FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'menu_date' => '2026-10-15',
            'meal_type' => 'lunch',
            'name' => 'Meals',
            'price' => 50,
            'is_available' => true,
        ]);

        $this->actingAs($this->schoolAdmin)->post(route('school.food-order.items.store', [
            'tenantId' => $this->school->id,
            'event' => $this->event->id,
        ]), ['menu_item_id' => $menuItem->id, 'quantity' => 10]);

        $issueResp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.issue-from-bill', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
        ]));
        $issueResp->assertRedirect();
        $issueResp->assertSessionHas('success');

        $coupon = \App\Models\FestFoodCoupon::where('event_id', $this->event->id)->firstOrFail();
        $this->assertSame('issued', $coupon->status);
        $this->assertSame(10, $coupon->head_count);

        $redeemResp = $this->actingAs($this->sahodayaAdmin)->post(route('sahodaya.events.food-coupons.redeem', [
            'tenantId' => $this->sahodaya->id,
            'event' => $this->event->id,
            'coupon' => $coupon->id,
        ]));
        $redeemResp->assertRedirect();
        $this->assertSame('redeemed', $coupon->fresh()->status);
        $this->assertNotNull($coupon->fresh()->redeemed_at);
    }
}

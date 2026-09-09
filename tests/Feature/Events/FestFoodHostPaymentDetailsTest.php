<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestFoodMenuItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A school can enter its own bank/UPI details (App\Models\Tenant::paymentDetails(),
 * saved via SchoolAdmin\SettingsController) so that when it's designated the "host
 * school" for an event's food payments, ordering schools actually see where to send
 * money — closing the gap where FestFoodOrderController::show() previously only showed
 * a payee NAME with no bank/UPI/QR at all.
 */
class FestFoodHostPaymentDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $sahodaya;

    private Tenant $orderingSchool;

    private User $orderingSchoolAdmin;

    private FestEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Host Payment Details Sahodaya',
            'domain' => 'host-payment-'.Str::random(8).'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $this->sahodaya->id,
            'prefix' => 'HP',
            'student_data_mode' => 'counts_only',
            'payment_bank_name' => 'Sahodaya Bank',
            'payment_account_no' => '1112223334',
            'payment_ifsc' => 'SAHO0001111',
            'payment_upi' => 'sahodaya@upi',
        ]);

        $this->orderingSchool = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Ordering School',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $this->orderingSchoolAdmin = User::factory()->create(['tenant_id' => $this->orderingSchool->id, 'email_verified_at' => now()]);
        $this->orderingSchoolAdmin->assignRole('school_admin');

        $this->event = FestEvent::create([
            'tenant_id' => $this->sahodaya->id,
            'title' => 'Host Payment Details Fest',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'ongoing',
        ]);

        FestFoodMenuItem::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $this->event->id,
            'menu_date' => '2026-09-01',
            'meal_type' => 'lunch',
            'name' => 'Meals',
            'price' => 100,
            'is_available' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_a_school_can_save_its_own_payment_details(): void
    {
        $response = $this->actingAs($this->orderingSchoolAdmin)->post(route('school.settings.update', [
            'tenantId' => $this->orderingSchool->id,
        ]), [
            'phone' => '9876543210',
            'email' => 'school@example.test',
            'address' => 'Somewhere',
            'payment_bank_name' => 'Ordering School Bank',
            'payment_account_no' => '5556667778',
            'payment_ifsc' => 'ORDR0002222',
            'payment_upi' => 'orderingschool@upi',
        ]);

        $response->assertRedirect();
        $details = $this->orderingSchool->fresh()->paymentDetails();
        $this->assertSame('Ordering School Bank', $details['bank_name']);
        $this->assertSame('5556667778', $details['account_no']);
        $this->assertSame('ORDR0002222', $details['ifsc']);
        $this->assertSame('orderingschool@upi', $details['upi']);
    }

    public function test_ordering_school_sees_the_host_schools_payment_details_once_designated(): void
    {
        $hostSchool = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Host School',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $hostSchool->setSetting('payment', [
            'bank_name' => 'Host Bank', 'account_no' => '9998887776', 'ifsc' => 'HOST0003333', 'upi' => 'host@upi',
        ]);

        $this->event->update(['food_payee_type' => 'host_school', 'food_host_school_id' => $hostSchool->id]);

        $response = $this->actingAs($this->orderingSchoolAdmin)->get(route('school.food-order.show', [
            'tenantId' => $this->orderingSchool->id, 'event' => $this->event->id,
        ]));

        $response->assertOk();
        // Tenant::getNameAttribute() uppercases school-type tenant names on read.
        $response->assertInertia(fn ($page) => $page
            ->where('payeeDetails.name', 'HOST SCHOOL')
            ->where('payeeDetails.bank_name', 'Host Bank')
            ->where('payeeDetails.account_no', '9998887776')
            ->where('payeeDetails.ifsc', 'HOST0003333')
            ->where('payeeDetails.upi', 'host@upi'));
    }

    public function test_ordering_school_sees_the_sahodayas_own_payment_details_by_default(): void
    {
        $response = $this->actingAs($this->orderingSchoolAdmin)->get(route('school.food-order.show', [
            'tenantId' => $this->orderingSchool->id, 'event' => $this->event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('payeeDetails.bank_name', 'Sahodaya Bank')
            ->where('payeeDetails.upi', 'sahodaya@upi'));
    }

    public function test_no_payee_details_card_when_the_host_school_has_not_entered_any(): void
    {
        $hostSchool = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Host School With No Bank Details',
            'parent_id' => $this->sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $this->event->update(['food_payee_type' => 'host_school', 'food_host_school_id' => $hostSchool->id]);

        $response = $this->actingAs($this->orderingSchoolAdmin)->get(route('school.food-order.show', [
            'tenantId' => $this->orderingSchool->id, 'event' => $this->event->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('payeeDetails', null));
    }
}

<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Sahodaya admin can enter the host school's receiving account (bank/IFSC/UPI) from
 * the Food Menu page itself -- previously only the school's own admin could, under School
 * Settings, so a host school that never filled it in left ordering schools with nothing
 * to pay to.
 */
class FestFoodMenuHostAccountTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Host Account Sahodaya',
            'domain' => 'host-account-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'HA', 'student_data_mode' => 'counts_only']);

        $host = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Host School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Host Account Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return compact('sahodaya', 'host', 'event', 'admin');
    }

    public function test_admin_can_save_the_host_schools_account_details_from_the_food_menu(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['admin'])->put(route('sahodaya.events.food-menu.payee.update', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), [
            'food_payee_type' => 'host_school',
            'food_host_school_id' => $f['host']->id,
            'payment_bank_name' => 'State Bank of India',
            'payment_account_no' => '1234567890',
            'payment_ifsc' => 'SBIN0000001',
            'payment_upi' => 'host@upi',
        ])->assertSessionHasNoErrors();

        $details = $f['host']->fresh()->paymentDetails();
        $this->assertSame('State Bank of India', $details['bank_name']);
        $this->assertSame('1234567890', $details['account_no']);
        $this->assertSame('SBIN0000001', $details['ifsc']);
        $this->assertSame('host@upi', $details['upi']);
        $this->assertSame($f['host']->id, $f['event']->fresh()->food_host_school_id);
    }

    public function test_food_menu_page_exposes_existing_host_details_for_prefill(): void
    {
        $f = $this->fixture();
        $f['host']->setSetting('payment', ['bank_name' => 'Federal Bank', 'account_no' => '999']);

        $response = $this->actingAs($f['admin'])->get(route('sahodaya.events.food-menu.index', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]));

        $response->assertOk();
        $details = $response->viewData('page')['props']['schoolPaymentDetails'];
        $this->assertSame('Federal Bank', $details[$f['host']->id]['bank_name']);
    }

    public function test_account_details_are_ignored_when_the_payee_is_the_sahodaya(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['admin'])->put(route('sahodaya.events.food-menu.payee.update', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]), [
            'food_payee_type' => 'sahodaya',
            'food_host_school_id' => $f['host']->id,
            'payment_bank_name' => 'Should Not Save',
        ])->assertSessionHasNoErrors();

        $this->assertNull($f['host']->fresh()->paymentDetails()['bank_name']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AccountHead;
use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahodaya_admin_can_identify_school_and_record_missing_overpayment_credit(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, SahodayaMasterDataSeeder::class]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'reconciliation.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'REC',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Reconciliation School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Chess 2026-27',
            'event_type' => 'sports',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);
        $fee = FestSchoolEventFee::create([
            'event_id' => $event->id,
            'school_id' => $school->id,
            'total_due' => 1800,
            'amount_paid' => 2400,
            'status' => 'approved',
        ]);
        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'amount' => 2400,
            'file_path' => 'tests/reconciliation-proof.png',
            'status' => FeeReceipt::STATUS_APPROVED,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'EF-TEST-1',
        ]);
        $fee->update(['fee_receipt_id' => $receipt->id]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/finance/payment-reconciliation?event_id={$event->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Finance/PaymentReconciliation', false)
                ->where('rows.0.school_name', 'Reconciliation School')
                ->where('rows.0.unreconciled', 600)
                ->where('rows.0.program', 'Chess 2026-27'));

        $this->actingAs($admin)
            ->post("/sahodaya-admin/{$sahodaya->id}/finance/payment-reconciliation/record-credit", [
                'carrier_type' => 'fest',
                'carrier_id' => $fee->id,
                'reason' => 'Historical Chess overpayment',
            ])
            ->assertRedirect();

        $credit = FestFeeCredit::where('fest_school_event_fee_id', $fee->id)->sole();
        $this->assertSame(600.0, (float) $credit->amount);

        $payable = AccountHead::where('tenant_id', $sahodaya->id)
            ->where('code', 'FEE-CREDIT-PAYABLE')
            ->sole();
        $this->assertSame(
            600.0,
            (float) LedgerTransaction::where('account_head_id', $payable->id)
                ->where('entry_type', 'credit')
                ->sum('amount')
        );

        $this->actingAs($admin)
            ->post("/sahodaya-admin/{$sahodaya->id}/finance/payment-reconciliation/record-credit", [
                'carrier_type' => 'fest',
                'carrier_id' => $fee->id,
            ])
            ->assertStatus(422);

        $this->assertSame(1, FestFeeCredit::where('fest_school_event_fee_id', $fee->id)->count());
    }
}

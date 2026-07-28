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
use App\Services\Ledger\FeeReceiptLedgerDispatcher;
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

    public function test_admin_can_reverse_one_selected_approved_event_receipt_with_reason(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, SahodayaMasterDataSeeder::class]);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Receipt Sahodaya',
            'domain' => 'receipt-reversal.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RVR',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Receipt School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Chess',
            'event_type' => 'sports',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);
        $fee = FestSchoolEventFee::create([
            'event_id' => $event->id,
            'school_id' => $school->id,
            'total_due' => 3000,
            'amount_paid' => 5400,
            'status' => 'approved',
        ]);
        $first = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => 'tests/first-proof.png',
            'amount' => 2400,
            'status' => FeeReceipt::STATUS_APPROVED,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'EF-FIRST',
        ]);
        $second = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => 'tests/second-proof.png',
            'amount' => 3000,
            'status' => FeeReceipt::STATUS_APPROVED,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'EF-SECOND',
        ]);
        $fee->update(['fee_receipt_id' => $second->id]);
        app(FeeReceiptLedgerDispatcher::class)->postApproved($first, $sahodaya->id);
        app(FeeReceiptLedgerDispatcher::class)->postApproved($second, $sahodaya->id);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->post(
                "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/school-fees/{$fee->id}/receipts/{$first->id}/reject",
                ['rejection_reason' => 'Duplicate payment entry'],
            )
            ->assertRedirect();

        $this->assertSame(FeeReceipt::STATUS_REVERSED, $first->fresh()->status);
        $this->assertSame('Duplicate payment entry', $first->fresh()->reversal_reason);
        $this->assertSame(FeeReceipt::STATUS_APPROVED, $second->fresh()->status);
        $this->assertSame($second->id, $fee->fresh()->fee_receipt_id);
        $this->assertSame(3000.0, (float) $fee->fresh()->amount_paid);
        $this->assertSame('approved', $fee->fresh()->status);
        $this->assertSame(
            2,
            LedgerTransaction::where('reference_type', FeeReceipt::REVERSAL_REFERENCE)
                ->where('reference_id', $first->id)
                ->count(),
        );
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperadmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        return $superadmin;
    }

    private function makeInvoiceAwaitingApproval(string $billingPeriod): SubscriptionReceipt
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya '.$billingPeriod,
            'is_active' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => ucfirst($billingPeriod).' Plan',
            'slug' => $billingPeriod.'-plan-'.Str::random(6),
            'price_inr' => 1000,
            'billing_period' => $billingPeriod,
            'is_active' => true,
        ]);

        $invoice = SubscriptionInvoice::create([
            'invoice_number' => SubscriptionInvoice::generateNumber(),
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'amount' => 1000,
            'due_date' => now()->addDays(7),
            'status' => 'sent',
        ]);

        return SubscriptionReceipt::create([
            'invoice_id' => $invoice->id,
            'file_path' => 'receipts/test.pdf',
            'status' => 'submitted',
        ]);
    }

    public function test_approving_a_monthly_plan_receipt_extends_subscription_by_one_month(): void
    {
        $superadmin = $this->actingSuperadmin();
        $receipt = $this->makeInvoiceAwaitingApproval('monthly');

        $this->actingAs($superadmin)
            ->post("/admin/billing/receipts/{$receipt->id}/approve")
            ->assertRedirect();

        $subscription = TenantSubscription::where('tenant_id', $receipt->invoice->tenant_id)->firstOrFail();

        $this->assertSame(
            now()->addMonthNoOverflow()->toDateString(),
            $subscription->period_end->toDateString()
        );
    }

    public function test_approving_an_annual_plan_receipt_extends_subscription_by_one_year(): void
    {
        $superadmin = $this->actingSuperadmin();
        $receipt = $this->makeInvoiceAwaitingApproval('annual');

        $this->actingAs($superadmin)
            ->post("/admin/billing/receipts/{$receipt->id}/approve")
            ->assertRedirect();

        $subscription = TenantSubscription::where('tenant_id', $receipt->invoice->tenant_id)->firstOrFail();

        $this->assertSame(
            now()->addYear()->toDateString(),
            $subscription->period_end->toDateString()
        );
    }

    public function test_approve_receipt_records_reviewer_and_can_be_read_back_via_fk(): void
    {
        $superadmin = $this->actingSuperadmin();
        $receipt = $this->makeInvoiceAwaitingApproval('annual');

        $this->actingAs($superadmin)
            ->post("/admin/billing/receipts/{$receipt->id}/approve")
            ->assertRedirect();

        $receipt->refresh();

        $this->assertSame('approved', $receipt->status);
        $this->assertSame($superadmin->id, $receipt->reviewed_by);
        $this->assertSame($superadmin->id, $receipt->reviewedBy->id);
    }
}

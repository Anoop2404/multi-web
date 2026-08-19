<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformDashboardSnapshot;
use App\Models\PlatformUser;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperadmin(): PlatformUser
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = PlatformUser::query()->create([
            'name' => 'Reports Super',
            'email' => 'reports-super@example.com',
            'username' => 'reports_super',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        return $superadmin;
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Reports Tenant', 'is_active' => true,
        ]);
    }

    public function test_index_returns_recent_snapshots_newest_first(): void
    {
        $superadmin = $this->actingSuperadmin();

        PlatformDashboardSnapshot::create([
            'total_students' => 10, 'total_teachers' => 1, 'revenue_this_month_inr' => 0,
            'sahodayas_included' => 1, 'sahodayas_total' => 1, 'computed_at' => now()->subDay(),
        ]);
        $newest = PlatformDashboardSnapshot::create([
            'total_students' => 20, 'total_teachers' => 2, 'revenue_this_month_inr' => 0,
            'sahodayas_included' => 1, 'sahodayas_total' => 1, 'computed_at' => now(),
        ]);

        $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index', false)
                ->where('snapshots.0.id', $newest->id)
                ->where('snapshots.0.total_students', 20)
            );
    }

    public function test_revenue_by_month_covers_twelve_months_and_sums_approved_invoices(): void
    {
        $superadmin = $this->actingSuperadmin();
        $tenant = $this->makeTenant();

        SubscriptionInvoice::create([
            'invoice_number' => 'INV-A', 'tenant_id' => $tenant->id, 'amount' => 1000,
            'due_date' => now(), 'status' => 'approved', 'approved_at' => now(),
        ]);
        SubscriptionInvoice::create([
            'invoice_number' => 'INV-B', 'tenant_id' => $tenant->id, 'amount' => 500,
            'due_date' => now(), 'status' => 'approved', 'approved_at' => now(),
        ]);
        // Outside the 12-month window — must not be included.
        SubscriptionInvoice::create([
            'invoice_number' => 'INV-OLD', 'tenant_id' => $tenant->id, 'amount' => 99999,
            'due_date' => now(), 'status' => 'approved', 'approved_at' => now()->subMonths(13),
        ]);
        // Not approved — must not be included.
        SubscriptionInvoice::create([
            'invoice_number' => 'INV-PENDING', 'tenant_id' => $tenant->id, 'amount' => 250,
            'due_date' => now(), 'status' => 'pending_payment',
        ]);

        $response = $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/reports')
            ->assertOk();

        $response->assertInertia(function (Assert $page) {
            $page->component('Admin/Reports/Index', false)
                ->has('revenueByMonth', 12)
                ->where('revenueByMonth.11.revenue_inr', fn ($v) => (float) $v === 1500.0)
                ->where('revenueByMonth.11.month', now()->format('M Y'));
        });
    }

    public function test_subscription_status_breakdown_counts_each_status(): void
    {
        $superadmin = $this->actingSuperadmin();

        foreach (['active', 'active', 'grace', 'suspended'] as $status) {
            TenantSubscription::create([
                'tenant_id' => $this->makeTenant()->id,
                'period_start' => now(), 'period_end' => now()->addYear(),
                'status' => $status,
            ]);
        }

        $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index', false)
                ->where('subscriptionStatusBreakdown.active', 2)
                ->where('subscriptionStatusBreakdown.grace', 1)
                ->where('subscriptionStatusBreakdown.suspended', 1)
            );
    }

    public function test_export_snapshots_streams_an_xlsx_file(): void
    {
        $superadmin = $this->actingSuperadmin();
        PlatformDashboardSnapshot::create([
            'total_students' => 5, 'total_teachers' => 1, 'revenue_this_month_inr' => 0,
            'sahodayas_included' => 1, 'sahodayas_total' => 1, 'computed_at' => now(),
        ]);

        $response = $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/reports/snapshots/export');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }
}

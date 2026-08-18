<?php

namespace Tests\Unit\Services\Reports;

use App\Models\PlatformDashboardSnapshot;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SubscriptionInvoice;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Services\Reports\PlatformDashboardSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformDashboardSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.database_per_sahodaya' => false]);
    }

    private function makeSahodaya(): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Snapshot Sahodaya',
            'is_active' => true,
        ]);
    }

    public function test_compute_counts_students_and_teachers(): void
    {
        $sahodaya = $this->makeSahodaya();
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Snapshot School',
            'parent_id' => $sahodaya->id,
            'is_active' => true,
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 1']);

        Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Student One']);
        Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Student Two']);
        Teacher::create(['tenant_id' => $school->id, 'name' => 'Teacher One', 'status' => 'active']);

        $snapshot = (new PlatformDashboardSnapshotService)->compute();

        $this->assertSame(2, $snapshot->total_students);
        $this->assertSame(1, $snapshot->total_teachers);
        $this->assertSame(1, $snapshot->sahodayas_included);
        $this->assertSame(1, $snapshot->sahodayas_total);
        $this->assertNotNull($snapshot->computed_at);
    }

    public function test_compute_sums_only_approved_invoices_for_the_current_month(): void
    {
        $tenant = $this->makeSahodaya();

        SubscriptionInvoice::create([
            'invoice_number' => 'INV-CURRENT-APPROVED',
            'tenant_id' => $tenant->id,
            'amount' => 1000,
            'due_date' => now(),
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        SubscriptionInvoice::create([
            'invoice_number' => 'INV-CURRENT-PENDING',
            'tenant_id' => $tenant->id,
            'amount' => 500,
            'due_date' => now(),
            'status' => 'pending_payment',
        ]);
        SubscriptionInvoice::create([
            'invoice_number' => 'INV-LAST-MONTH-APPROVED',
            'tenant_id' => $tenant->id,
            'amount' => 2000,
            'due_date' => now()->subMonth(),
            'status' => 'approved',
            'approved_at' => now()->subMonth(),
        ]);

        $snapshot = (new PlatformDashboardSnapshotService)->compute();

        $this->assertSame('1000.00', $snapshot->revenue_this_month_inr);
    }

    public function test_latest_returns_the_most_recently_computed_snapshot(): void
    {
        PlatformDashboardSnapshot::create([
            'total_students' => 10, 'total_teachers' => 1,
            'revenue_this_month_inr' => 0, 'sahodayas_included' => 0, 'sahodayas_total' => 0,
            'computed_at' => now()->subDay(),
        ]);
        $newest = PlatformDashboardSnapshot::create([
            'total_students' => 20, 'total_teachers' => 2,
            'revenue_this_month_inr' => 0, 'sahodayas_included' => 0, 'sahodayas_total' => 0,
            'computed_at' => now(),
        ]);

        $latest = (new PlatformDashboardSnapshotService)->latest();

        $this->assertSame($newest->id, $latest->id);
    }

    public function test_latest_returns_null_when_no_snapshot_exists(): void
    {
        $this->assertNull((new PlatformDashboardSnapshotService)->latest());
    }
}

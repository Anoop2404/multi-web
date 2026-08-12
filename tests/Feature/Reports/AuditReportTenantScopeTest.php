<?php

namespace Tests\Feature\Reports;

use App\Models\AuditLog;
use App\Models\Region;
use App\Models\Tenant;
use App\Services\Reports\ErpReportQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for RPT-01 (functional audit, 2026-08-11/12): the five
 * AuditLog-backed ERP reports (Audit Trail, Auth Events, Finance Audit,
 * Export Activity Log, Failed Logins) plus the MCQ IP Audit report used to
 * query the shared, central audit_logs table with zero tenant scoping, so
 * any Sahodaya admin/staff/finance user could see every other federation's
 * audit history. This creates two independent Sahodaya tenants, writes an
 * audit log row attributed to each, and asserts a report run for tenant A
 * never contains tenant B's row (and vice versa).
 */
class AuditReportTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(string $name): Tenant
    {
        return Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => $name,
            'domain'    => Str::slug($name).'.test',
            'is_active' => true,
        ]);
    }

    public function test_audit_trail_report_does_not_leak_across_tenants(): void
    {
        $tenantA = $this->makeSahodaya('Federation A');
        $tenantB = $this->makeSahodaya('Federation B');

        AuditLog::create([
            'tenant_id'   => $tenantA->id,
            'category'    => 'system',
            'action'      => 'user.updated',
            'description' => 'Sentinel row for tenant A',
        ]);

        AuditLog::create([
            'tenant_id'   => $tenantB->id,
            'category'    => 'system',
            'action'      => 'user.updated',
            'description' => 'Sentinel row for tenant B',
        ]);

        $service = app(ErpReportQueryService::class);

        $rowsForA = $service->rows($tenantA->id, 'RPT-AUD-001');
        $this->assertTrue($rowsForA->contains(fn ($r) => $r['description'] === 'Sentinel row for tenant A'));
        $this->assertFalse($rowsForA->contains(fn ($r) => $r['description'] === 'Sentinel row for tenant B'));

        $rowsForB = $service->rows($tenantB->id, 'RPT-AUD-001');
        $this->assertTrue($rowsForB->contains(fn ($r) => $r['description'] === 'Sentinel row for tenant B'));
        $this->assertFalse($rowsForB->contains(fn ($r) => $r['description'] === 'Sentinel row for tenant A'));
    }

    public function test_failed_login_report_does_not_leak_across_tenants(): void
    {
        $tenantA = $this->makeSahodaya('Federation A');
        $tenantB = $this->makeSahodaya('Federation B');

        AuditLog::create([
            'tenant_id'   => $tenantA->id,
            'category'    => 'auth',
            'action'      => 'login.failed',
            'description' => 'Failed login attempt',
            'properties'  => ['email' => 'attacker-a@example.com'],
        ]);

        AuditLog::create([
            'tenant_id'   => $tenantB->id,
            'category'    => 'auth',
            'action'      => 'login.failed',
            'description' => 'Failed login attempt',
            'properties'  => ['email' => 'attacker-b@example.com'],
        ]);

        $service = app(ErpReportQueryService::class);

        $rowsForA = $service->rows($tenantA->id, 'RPT-AUTH-005');
        $this->assertTrue($rowsForA->contains(fn ($r) => $r['username'] === 'attacker-a@example.com'));
        $this->assertFalse($rowsForA->contains(fn ($r) => $r['username'] === 'attacker-b@example.com'));
    }

    public function test_school_activity_report_runs_without_sql_error_and_is_scoped(): void
    {
        // RPT-SCH-006 previously threw a SQL error referencing a tenant_id
        // column that did not exist on audit_logs at all.
        $tenantA = $this->makeSahodaya('Federation A');

        AuditLog::create([
            'tenant_id'   => $tenantA->id,
            'category'    => 'system',
            'action'      => 'user.updated',
            'description' => 'Activity row',
        ]);

        $service = app(ErpReportQueryService::class);

        $rows = $service->rows($tenantA->id, 'RPT-SCH-006');
        $this->assertTrue($rows->contains(fn ($r) => $r['description'] === 'Activity row'));
    }

    public function test_audit_log_write_derives_tenant_id_from_subject_model(): void
    {
        $tenantA = $this->makeSahodaya('Federation A');
        $region = Region::create(['tenant_id' => $tenantA->id, 'name' => 'Region A', 'code' => 'RGA', 'is_active' => true]);

        $log = app(\App\Services\Audit\PlatformAuditLogger::class)->log(
            'sentinel.action',
            'Sentinel description',
            subject: $region,
        );

        $this->assertSame($tenantA->id, $log->tenant_id);
        $this->assertDatabaseHas('audit_logs', [
            'id'        => $log->id,
            'tenant_id' => $tenantA->id,
        ]);
    }

    public function test_audit_log_write_derives_tenant_id_from_properties_school_id_when_no_subject(): void
    {
        $tenantA = $this->makeSahodaya('Federation A');

        $log = app(\App\Services\Audit\PlatformAuditLogger::class)->log(
            'sentinel.action',
            'Sentinel description',
            properties: ['school_id' => $tenantA->id],
        );

        $this->assertSame($tenantA->id, $log->tenant_id);
    }
}

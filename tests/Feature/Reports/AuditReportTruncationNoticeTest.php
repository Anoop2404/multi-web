<?php

namespace Tests\Feature\Reports;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Reports\ErpReportQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression test for the "fix silent-truncation limit()-before-filter pattern" item
 * of the functional audit's action plan (2026-08-11/12): RPT-AUD-001..004 cap at 500
 * rows even after RPT-01 correctly scoped them to the requesting Sahodaya — previously
 * nothing told the user when their date range matched more than 500 rows. See
 * QueriesExtendedReports::withTruncationNotice().
 */
class AuditReportTruncationNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_trail_report_appends_a_truncation_notice_past_500_rows(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => 'sahodaya-trunc',
            'type'      => 'sahodaya',
            'name'      => 'Truncation Sahodaya',
            'domain'    => 'truncation-test.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'TR',
            'student_data_mode' => 'counts_only',
        ]);

        $now = now();
        $rows = [];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = [
                'tenant_id'   => $sahodaya->id,
                'category'    => 'general',
                'action'      => 'test.action',
                'description' => "Row {$i}",
                'properties'  => json_encode(['user' => 'tester']),
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        DB::table('audit_logs')->insert($rows);

        $result = app(ErpReportQueryService::class)->rows($sahodaya->id, 'RPT-AUD-001', [
            'from' => $now->copy()->subDay()->toDateString(),
            'to'   => $now->copy()->addDay()->toDateString(),
        ]);

        // 500 real rows + exactly one synthetic notice row, not 501 silently cut to 500
        // with no indication.
        $this->assertCount(501, $result);
        $this->assertSame('⚠ Truncated', $result->last()['action']);
        $this->assertStringContainsString('501', $result->last()['description']);
    }

    public function test_audit_trail_report_has_no_notice_row_when_under_the_cap(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => 'sahodaya-no-trunc',
            'type'      => 'sahodaya',
            'name'      => 'No Truncation Sahodaya',
            'domain'    => 'no-truncation-test.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'NT',
            'student_data_mode' => 'counts_only',
        ]);

        DB::table('audit_logs')->insert([
            'tenant_id'   => $sahodaya->id,
            'category'    => 'general',
            'action'      => 'test.action',
            'description' => 'Single row',
            'properties'  => json_encode(['user' => 'tester']),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $result = app(ErpReportQueryService::class)->rows($sahodaya->id, 'RPT-AUD-001');

        $this->assertCount(1, $result);
        $this->assertNotSame('⚠ Truncated', $result->last()['action']);
    }
}

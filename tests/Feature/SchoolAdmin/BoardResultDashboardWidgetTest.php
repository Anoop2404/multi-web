<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoardResultDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'dash-widget-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'TS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        AcademicYearRecord::firstOrCreate(
            ['label' => '2026-27'],
            ['start_date' => '2026-06-01', 'end_date' => '2027-05-31', 'status' => 'active']
        );

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'admin');
    }

    public function test_dashboard_loads_with_no_board_results(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['school' => $school, 'admin' => $admin] = $this->makeSchool();

        $this->actingAs($admin)->get("/school-admin/{$school->id}")->assertOk();
    }

    public function test_dashboard_surfaces_certification_package_status_for_a_pending_result(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'admin' => $admin] = $this->makeSchool();

        $result = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'examination_type' => BoardResult::examinationTypeForClass(10),
            'academic_year' => '2026-27',
            'total_appeared' => 10,
            'pass_count' => 10,
            'pass_percent' => 100.0,
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        BoardResultCertificationPackage::create([
            'board_result_id' => $result->id,
            'tenant_id' => $school->id,
            'academic_year' => '2026-27',
            'class' => 10,
            'version' => 1,
            'status' => BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES,
        ]);

        $response = $this->actingAs($admin)->get("/school-admin/{$school->id}");
        $response->assertOk();

        $widget = $response->viewData('page')['props']['boardResultsWidget'];
        $this->assertSame(1, $widget['pending_count']);
        $this->assertCount(1, $widget['pending_results']);
        $this->assertSame(
            BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES,
            $widget['pending_results'][0]['certification_package']['status']
        );
    }
}
